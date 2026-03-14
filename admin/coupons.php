<?php
// admin/coupons.php - Coupon Management Interface
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
    <title>Coupon Codes - Velona Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin-styles.css" rel="stylesheet">
    <style>
        .coupon-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: #495057;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #6c757d; }
        .discount-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .usage-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }
        .usage-fill {
            height: 100%;
            background: linear-gradient(45deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-1">Coupon Codes</h1>
                <p class="text-muted">Create and manage discount coupons</p>
            </div>
            <button class="btn btn-primary" onclick="showAddCouponModal()">
                <i class="fas fa-plus me-2"></i>Create New Coupon
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-ticket-alt fa-2x mb-2 text-primary"></i>
                        <h3 class="mb-1" id="totalCoupons">0</h3>
                        <p class="mb-0">Total Coupons</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <h3 class="mb-1" id="activeCoupons">0</h3>
                        <p class="mb-0">Active Coupons</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x mb-2 text-info"></i>
                        <h3 class="mb-1" id="totalUsage">0</h3>
                        <p class="mb-0">Total Usage</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2 text-warning"></i>
                        <h3 class="mb-1" id="avgDiscount">0%</h3>
                        <p class="mb-0">Avg Discount</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Search Coupons</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Code, description...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="filterCoupons()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="expired">Expired</option>
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
                    <div class="col-md-5 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-fill" onclick="searchCoupons()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-times"></i>
                            </button>
                            <button class="btn btn-success" onclick="exportCoupons()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coupons Table -->
        <div class="card">
            <div class="card-body">
                <div class="loading text-center" id="tableLoading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading coupons...</p>
                </div>
                
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover" id="couponsTable" style="display: none;">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Description</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="couponsTableBody">
                            <!-- Coupons will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Coupons pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination will be loaded here -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Add/Edit Coupon Modal -->
    <div class="modal fade" id="couponModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="couponModalTitle">Create New Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="couponForm">
                    <input type="hidden" id="couponId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Coupon Code *</label>
                            <input type="text" class="form-control text-uppercase" id="couponCode" required maxlength="50" placeholder="e.g., SAVE20">
                            <div class="form-text">Code will be automatically converted to uppercase</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Discount Percentage *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="discountPercentage" required min="1" max="99" step="0.01" placeholder="10">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="couponDescription" rows="2" placeholder="Brief description of this coupon..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usage Limit</label>
                                <input type="number" class="form-control" id="usageLimit" min="1" placeholder="Leave empty for unlimited">
                                <div class="form-text">Empty = unlimited usage</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expires At</label>
                                <input type="datetime-local" class="form-control" id="expiresAt">
                                <div class="form-text">Empty = never expires</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active (can be used by customers)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Coupon
                        </button>
                    </div>
                </form>
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
            loadCoupons();
            loadStats();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('couponForm').addEventListener('submit', handleCouponSave);
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchCoupons();
                }
            });
            
            // Auto-uppercase coupon code
            document.getElementById('couponCode').addEventListener('input', function(e) {
                e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        // Load coupons with pagination and filters
        async function loadCoupons() {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: 'get_coupons',
                    page: currentPage,
                    per_page: itemsPerPage,
                    ...currentFilters
                });
                
                const response = await fetch(`api/manage-coupons.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    displayCoupons(data.coupons);
                    updatePagination(data.pagination);
                    document.getElementById('couponsTable').style.display = 'table';
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading coupons:', error);
                showAlert('Error loading coupons', 'danger');
            } finally {
                showLoading(false);
            }
        }

        // Display coupons in table
        function displayCoupons(coupons) {
            const tbody = document.getElementById('couponsTableBody');
            tbody.innerHTML = '';
            
            coupons.forEach(coupon => {
                const row = document.createElement('tr');
                
                // Determine status
                let status = 'active';
                let statusClass = 'status-active';
                if (!coupon.is_active) {
                    status = 'inactive';
                    statusClass = 'status-inactive';
                } else if (coupon.expires_at && new Date(coupon.expires_at) < new Date()) {
                    status = 'expired';
                    statusClass = 'status-expired';
                }
                
                // Usage calculation
                const usagePercentage = coupon.usage_limit ? 
                    Math.min(100, (coupon.used_count / coupon.usage_limit) * 100) : 0;
                const usageText = coupon.usage_limit ? 
                    `${coupon.used_count}/${coupon.usage_limit}` : 
                    `${coupon.used_count} times`;
                
                row.innerHTML = `
                    <td>
                        <div class="coupon-code">${coupon.code}</div>
                    </td>
                    <td>
                        <span class="discount-badge">${coupon.discount_percentage}% OFF</span>
                    </td>
                    <td>
                        <span class="text-muted">${coupon.description || 'No description'}</span>
                    </td>
                    <td>
                        <div class="mb-1">${usageText}</div>
                        ${coupon.usage_limit ? `
                            <div class="usage-bar">
                                <div class="usage-fill" style="width: ${usagePercentage}%"></div>
                            </div>
                        ` : '<small class="text-muted">Unlimited</small>'}
                    </td>
                    <td>
                        <span class="status-badge ${statusClass}">${status}</span>
                    </td>
                    <td>
                        ${coupon.expires_at ? 
                            `<div>${new Date(coupon.expires_at).toLocaleDateString()}</div>
                             <small class="text-muted">${new Date(coupon.expires_at).toLocaleTimeString()}</small>` : 
                            '<span class="text-muted">Never</span>'
                        }
                    </td>
                    <td>
                        <div>${new Date(coupon.created_at).toLocaleDateString()}</div>
                        <small class="text-muted">${new Date(coupon.created_at).toLocaleTimeString()}</small>
                    </td>
                    <td>
                        <button class="action-btn btn-edit" onclick="editCoupon(${coupon.id})" title="Edit Coupon">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn btn-danger" onclick="deleteCoupon(${coupon.id}, '${coupon.code}')" title="Delete Coupon">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Show add coupon modal
        function showAddCouponModal() {
            document.getElementById('couponModalTitle').textContent = 'Create New Coupon';
            document.getElementById('couponForm').reset();
            document.getElementById('couponId').value = '';
            document.getElementById('isActive').checked = true;
            new bootstrap.Modal(document.getElementById('couponModal')).show();
        }

        // Edit coupon
        async function editCoupon(couponId) {
            try {
                const response = await fetch(`api/manage-coupons.php?action=get_coupon&id=${couponId}`);
                const data = await response.json();
                
                if (data.success) {
                    const coupon = data.coupon;
                    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
                    document.getElementById('couponId').value = coupon.id;
                    document.getElementById('couponCode').value = coupon.code;
                    document.getElementById('discountPercentage').value = coupon.discount_percentage;
                    document.getElementById('couponDescription').value = coupon.description || '';
                    document.getElementById('usageLimit').value = coupon.usage_limit || '';
                    document.getElementById('expiresAt').value = coupon.expires_at ? 
                        new Date(coupon.expires_at).toISOString().slice(0, 16) : '';
                    document.getElementById('isActive').checked = coupon.is_active == 1;
                    
                    new bootstrap.Modal(document.getElementById('couponModal')).show();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading coupon:', error);
                showAlert('Error loading coupon details', 'danger');
            }
        }

        // Handle coupon save
        async function handleCouponSave(e) {
            e.preventDefault();
            
            const couponId = document.getElementById('couponId').value;
            const isEdit = couponId !== '';
            
            const formData = new FormData();
            formData.append('action', isEdit ? 'update_coupon' : 'create_coupon');
            if (isEdit) formData.append('id', couponId);
            formData.append('code', document.getElementById('couponCode').value);
            formData.append('discount_percentage', document.getElementById('discountPercentage').value);
            formData.append('description', document.getElementById('couponDescription').value);
            formData.append('usage_limit', document.getElementById('usageLimit').value);
            formData.append('expires_at', document.getElementById('expiresAt').value);
            formData.append('is_active', document.getElementById('isActive').checked ? 1 : 0);
            
            try {
                const response = await fetch('api/manage-coupons.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(isEdit ? 'Coupon updated successfully!' : 'Coupon created successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('couponModal')).hide();
                    loadCoupons();
                    loadStats();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error saving coupon:', error);
                showAlert('Error saving coupon', 'danger');
            }
        }

        // Delete coupon
        async function deleteCoupon(couponId, couponCode) {
            if (!confirm(`Are you sure you want to delete coupon "${couponCode}"? This action cannot be undone.`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_coupon');
                formData.append('id', couponId);
                
                const response = await fetch('api/manage-coupons.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Coupon deleted successfully!', 'success');
                    loadCoupons();
                    loadStats();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error deleting coupon:', error);
                showAlert('Error deleting coupon', 'danger');
            }
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/manage-coupons.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalCoupons').textContent = data.stats.total_coupons;
                    document.getElementById('activeCoupons').textContent = data.stats.active_coupons;
                    document.getElementById('totalUsage').textContent = data.stats.total_usage;
                    document.getElementById('avgDiscount').textContent = data.stats.avg_discount + '%';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Search, filter, pagination functions
        function searchCoupons() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            if (searchTerm) {
                currentFilters.search = searchTerm;
            } else {
                delete currentFilters.search;
            }
            currentPage = 1;
            loadCoupons();
        }

        function filterCoupons() {
            const status = document.getElementById('statusFilter').value;
            
            currentFilters = {};
            if (status) currentFilters.status = status;
            
            currentPage = 1;
            loadCoupons();
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            currentFilters = {};
            currentPage = 1;
            loadCoupons();
        }

        function changeItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1;
            loadCoupons();
        }

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

        function changePage(page) {
            if (page >= 1 && page <= totalPages && page !== currentPage) {
                currentPage = page;
                loadCoupons();
            }
        }

        function exportCoupons() {
            const params = new URLSearchParams({
                action: 'export_coupons',
                ...currentFilters
            });
            window.open(`api/manage-coupons.php?${params}`, '_blank');
        }

        function showLoading(show) {
            const loading = document.getElementById('tableLoading');
            const table = document.getElementById('couponsTable');
            
            if (show) {
                loading.style.display = 'block';
                table.style.display = 'none';
            } else {
                loading.style.display = 'none';
                table.style.display = 'table';
            }
        }

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
</body>
</html>