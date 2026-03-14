<?php
// customers.php - Main customer management page

// Include necessary files for the page to function.
include('../includes/database.php');

// The API logic for fetching a single customer's details has been moved to a separate file (e.g., api/manage-customers.php) to prevent the "Invalid request" error on page load.
require_once 'admin-session.php';
// ONLY authentication check - Gateway control
requireAdminAuth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Bluefifth Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin-styles.css" rel="stylesheet">
    <style>
        .doc-img {
            border: 2px solid #eee;
            border-radius: 6px;
            padding: 3px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .doc-img:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-1">Customer Management</h1>
                <p class="text-muted">Manage customers and view their activity</p>
            </div>
            <button class="btn btn-primary" onclick="exportCustomers()">
                <i class="fas fa-download me-2"></i>Export Data
            </button>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalCustomers">0</h3>
                        <p class="mb-0">Total Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h3 class="mb-1" id="activeCustomers">0</h3>
                        <p class="mb-0">Active Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-wallet fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalWalletBalance">₹0</h3>
                        <p class="mb-0">Total Wallet Balance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-share-alt fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalReferrers">0</h3>
                        <p class="mb-0">Active Referrers</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search Customers</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Name or email...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="filterCustomers()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Has Orders</label>
                        <select class="form-select" id="ordersFilter" onchange="filterCustomers()">
                            <option value="">All</option>
                            <option value="yes">With Orders</option>
                            <option value="no">No Orders</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Has Referrals</label>
                        <select class="form-select" id="referralsFilter" onchange="filterCustomers()">
                            <option value="">All</option>
                            <option value="yes">With Referrals</option>
                            <option value="no">No Referrals</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-primary w-100" onclick="searchCustomers()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover" >
                        <thead class="table-dark">
                            <tr>
                                <th>Customer</th>
                                <th>Wallet Balance</th>
                                <th>Total Orders</th>
                                <th>Referral Stats</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customersTableBody">
                            </tbody>
                    </table>
                </div>
                
                <nav aria-label="Customers pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        </ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customerDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetailsBody">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentPage = 1;
        let itemsPerPage = 25;
        let totalPages = 1;
        let currentFilters = {};

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomers();
            loadStats();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchCustomers();
                }
            });
        }

        // Load customers
        async function loadCustomers() {
            try {
                const params = new URLSearchParams({
                    action: 'get_customers',
                    page: currentPage,
                    per_page: itemsPerPage,
                    ...currentFilters
                });
                
                const response = await fetch(`api/manage-customers.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    displayCustomers(data.customers);
                    updatePagination(data.pagination);
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading customers:', error);
                showAlert('Error loading customers', 'danger');
            }
        }

        // Display customers in table
        function displayCustomers(customers) {
            const tbody = document.getElementById('customersTableBody');
            tbody.innerHTML = '';
            
            customers.forEach(customer => {
                const row = document.createElement('tr');
                
                // Customer info
                const avatarHtml = customer.profile_image 
                    ? `<img src="${customer.profile_image}" alt="Avatar" class="customer-avatar">`
                    : `<div class="customer-avatar placeholder">${customer.name.charAt(0).toUpperCase()}</div>`;
                
                const walletBalance = parseFloat(customer.wallet_balance || 0);
                const totalOrders = parseInt(customer.total_orders || 0);
                const referralEarnings = parseFloat(customer.referral_earnings || 0);
                
                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            ${avatarHtml}
                            <div class="ms-3">
                                <div class="fw-bold">${customer.name}</div>
                                <small class="text-muted">${customer.email}</small>
                                <br><small class="text-muted">Joined: ${new Date(customer.created_at).toLocaleDateString()}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold">₹${walletBalance.toFixed(2)}</div>
                        <small class="text-muted">Total: ₹${parseFloat(customer.total_earned || 0).toFixed(2)}</small>
                        <br><small class="text-success">Claimed: ₹${parseFloat(customer.total_claimed || 0).toFixed(2)}</small>
                    </td>
                    <td>
                        <span class="badge ${totalOrders > 0 ? 'badge-success' : 'badge-warning'}">${totalOrders} orders</span>
                        ${totalOrders > 0 ? `<br><small class="text-muted">₹${parseFloat(customer.total_spent || 0).toFixed(2)} spent</small>` : ''}
                    </td>
                    <td>
                        ${customer.referral_code ? `
                            <div><strong>Code:</strong> ${customer.referral_code}</div>
                            <small class="text-muted">${customer.referral_count || 0} referrals</small>
                            <br><small class="text-success">₹${referralEarnings.toFixed(2)} earned</small>
                        ` : '<span class="text-muted">No referrals</span>'}
                    </td>
                    <td>
                        ${customer.last_login ? new Date(customer.last_login).toLocaleDateString() : 'Never'}
                    </td>
                    <td>
                        <span class="badge ${customer.status === 'active' ? 'badge-success' : 'badge-danger'}">
                            ${customer.status ? customer.status.toUpperCase() : 'ACTIVE'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewCustomer(${customer.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="toggleStatus(${customer.id}, '${customer.status || 'active'}')" title="Toggle Status">
                            <i class="fas fa-toggle-on"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Update pagination
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
                loadCustomers();
            }
        }

        // Search customers
        function searchCustomers() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            if (searchTerm) {
                currentFilters.search = searchTerm;
            } else {
                delete currentFilters.search;
            }
            currentPage = 1;
            loadCustomers();
        }

        // Filter customers
        function filterCustomers() {
            const status = document.getElementById('statusFilter').value;
            const orders = document.getElementById('ordersFilter').value;
            const referrals = document.getElementById('referralsFilter').value;
            
            currentFilters = {};
            if (status) currentFilters.status = status;
            if (orders) currentFilters.has_orders = orders;
            if (referrals) currentFilters.has_referrals = referrals;
            
            currentPage = 1;
            loadCustomers();
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/manage-customers.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalCustomers').textContent = data.stats.total_customers;
                    document.getElementById('activeCustomers').textContent = data.stats.active_customers;
                    document.getElementById('totalWalletBalance').textContent = '₹' + parseFloat(data.stats.total_wallet_balance).toLocaleString();
                    document.getElementById('totalReferrers').textContent = data.stats.total_referrers;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // View customer details
        async function viewCustomer(customerId) {
            try {
                // This call now goes to a dedicated API endpoint
                const response = await fetch(`api/manage-customers.php?action=get_customer&id=${customerId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayCustomerDetails(data.customer);
                    new bootstrap.Modal(document.getElementById('customerDetailsModal')).show();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading customer details:', error);
                showAlert('Error loading customer details', 'danger');
            }
        }

        // Display customer details in modal
function displayCustomerDetails(customer) {
    const detailsHtml = `
        <div class="row">
            <div class="col-md-4 text-center">
                ${customer.profile_image 
                    ? `<img src="${customer.profile_image}" alt="Avatar" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">`
                    : `<div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; background: linear-gradient(45deg, #667eea, #764ba2); color: white; font-size: 3rem; font-weight: bold;">${customer.name.charAt(0).toUpperCase()}</div>`
                }
                <h5>${customer.name}</h5>
                <p class="text-muted">${customer.email}</p>
            </div>
            <div class="col-md-8">
                <h6>Account Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Join Date:</strong></td><td>${new Date(customer.created_at).toLocaleDateString()}</td></tr>
                    <tr><td><strong>Last Login:</strong></td><td>${customer.last_login ? new Date(customer.last_login).toLocaleDateString() : 'Never'}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge ${customer.status === 'active' ? 'badge-success' : 'badge-danger'}">${(customer.status || 'active').toUpperCase()}</span></td></tr>
                </table>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <h6>Wallet & Earnings</h6>
                <table class="table table-sm">
                    <tr><td>Current Balance:</td><td>₹${parseFloat(customer.wallet_balance || 0).toFixed(2)}</td></tr>
                    <tr><td>Total Earned:</td><td>₹${parseFloat(customer.total_earned || 0).toFixed(2)}</td></tr>
                    <tr><td>Total Claimed:</td><td>₹${parseFloat(customer.total_claimed || 0).toFixed(2)}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Order History</h6>
                <table class="table table-sm">
                    <tr><td>Total Orders:</td><td>${customer.total_orders || 0}</td></tr>
                    <tr><td>Total Spent:</td><td>₹${parseFloat(customer.total_spent || 0).toFixed(2)}</td></tr>
                </table>
            </div>
        </div>
                ${customer.kyc_status ? `
            <div class="mt-3 mb-3">
                <h6>KYC Status: 
                    ${customer.kyc_status === 'verified' 
                        ? '<span class="badge badge-success">Verified</span>' 
                        : '<span class="badge badge-warning">Not Verified</span>'}
                </h6>
                <button class="btn btn-sm btn-success" onclick="updateKycStatus(${customer.id}, 'verified')">Verify</button>
                <button class="btn btn-sm btn-danger" onclick="updateKycStatus(${customer.id}, 'not_verified')">Unverify</button>
            </div>
        ` : ''}


        ${(customer.aadhar_front || customer.aadhar_back || customer.pan_front || customer.pan_back) ? `
            <div class="mt-4">
                <h6>KYC Documents</h6>
                <div class="row">
                    ${customer.aadhar_front ? `
                        <div class="col-md-6">
                            <strong>Aadhar Front:</strong><br>
                            <img src="${customer.aadhar_front}" class="img-fluid rounded mb-2 doc-img" style="max-height:150px;">
                            <br><a href="${customer.aadhar_front}" download class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>` : ''}
        
                    ${customer.aadhar_back ? `
                        <div class="col-md-6">
                            <strong>Aadhar Back:</strong><br>
                            <img src="${customer.aadhar_back}" class="img-fluid rounded mb-2 doc-img" style="max-height:150px;">
                            <br><a href="${customer.aadhar_back}" download class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>` : ''}
        
                    ${customer.pan_front ? `
                        <div class="col-md-6">
                            <strong>PAN Front:</strong><br>
                            <img src="${customer.pan_front}" class="img-fluid rounded mb-2 doc-img" style="max-height:150px;">
                            <br><a href="${customer.pan_front}" download class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>` : ''}
        
                    ${customer.pan_back ? `
                        <div class="col-md-6">
                            <strong>Bank passbook:</strong><br>
                            <img src="${customer.pan_back}" class="img-fluid rounded mb-2 doc-img" style="max-height:150px;">
                            <br><a href="${customer.pan_back}" download class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>` : ''}
                </div>
            </div>
        ` : ''}
        
        ${(customer.kyc_name || customer.kyc_aadhar_number || customer.kyc_pan_number || customer.kyc_bank_account || customer.kyc_ifsc) ? `
            <div class="mt-4">
                <h6>KYC Form Details</h6>
                <table class="table table-sm">
                    <tr><td><strong>Name:</strong></td><td>${customer.kyc_name || '-'}</td></tr>
                    <tr><td><strong>Aadhar Number:</strong></td><td>${customer.kyc_aadhar_number || '-'}</td></tr>
                    <tr><td><strong>PAN Number:</strong></td><td>${customer.kyc_pan_number || '-'}</td></tr>
                    <tr><td><strong>Bank Account:</strong></td><td>${customer.kyc_bank_account || '-'}</td></tr>
                    <tr><td><strong>IFSC Code:</strong></td><td>${customer.kyc_ifsc || '-'}</td></tr>
                </table>
            </div>
        ` : ''}
        


        ${customer.referral_code ? `
            <div class="mt-4">
                <h6>Referral Program</h6>
                <table class="table table-sm">
                    <tr><td>Referral Code:</td><td><code>${customer.referral_code}</code></td></tr>
                    <tr><td>Total Referrals:</td><td>${customer.referral_count || 0}</td></tr>
                    <tr><td>Referral Earnings:</td><td>₹${parseFloat(customer.referral_earnings || 0).toFixed(2)}</td></tr>
                </table>
            </div>
        ` : ''}

        ${customer.monthly_breakdown && customer.monthly_breakdown.length > 0 ? `
            <div class="mt-4">
                <h6>Monthly Referral Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Rate</th>
                                <th>Purchases</th>
                                <th>Sales</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${customer.monthly_breakdown.map(month => `
                                <tr>
                                    <td>Month ${month.purchase_month}</td>
                                    <td>${month.earning_rate}%</td>
                                    <td>${month.purchase_count}</td>
                                    <td>₹${parseFloat(month.month_sales).toFixed(2)}</td>
                                    <td>₹${parseFloat(month.month_points).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` : ''}
    `;
    
    document.getElementById('customerDetailsBody').innerHTML = detailsHtml;
}


        // Toggle customer status
        async function toggleStatus(customerId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this customer?`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('customer_id', customerId);
                formData.append('status', newStatus);
                
                const response = await fetch('api/manage-customers.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(`Customer ${newStatus}d successfully!`, 'success');
                    loadCustomers();
                    loadStats();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error toggling status:', error);
                showAlert('Error updating customer status', 'danger');
            }
        }

        // Export customers
        async function exportCustomers() {
            try {
                const params = new URLSearchParams({
                    action: 'export_customers',
                    ...currentFilters
                });
                
                window.open(`api/manage-customers.php?${params}`, '_blank');
            } catch (error) {
                console.error('Error exporting customers:', error);
                showAlert('Error exporting customers', 'danger');
            }
        }

        // Show alert
        function showAlert(message, type) {
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
    <script>
function updateKycStatus(userId, status) {
    if (!confirm("Are you sure you want to set KYC status to " + status + "?")) return;
    
    fetch('update_kyc_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'user_id=' + userId + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("KYC Status updated successfully!");
            location.reload(); // refresh to see new status
        } else {
            alert("Error: " + data.message);
        }
    });
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