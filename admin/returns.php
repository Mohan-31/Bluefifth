<?php
// admin/returns.php - Returns Management Interface
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
    <title>Returns Management - Velona Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin-styles.css" rel="stylesheet">
    <style>
        .return-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid #ddd;
        }
        .return-photo:hover {
            border-color: #007bff;
            transform: scale(1.05);
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-requested { background: #fff3cd; color: #856404; }
        .status-pickup_scheduled { background: #cce5ff; color: #004085; }
        .status-collected { background: #d4edda; color: #155724; }
        .status-received { background: #d1ecf1; color: #0c5460; }
        .status-processed { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .return-item {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            margin: 2px 0;
            font-size: 0.875rem;
        }
        .btn.disabled {
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
        }
        
    </style>
</head>
<body>
    <?php include 'admin-navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-1">Returns Management</h1>
                <p class="text-muted">Manage and track all return requests</p>
            </div>
            <button class="btn btn-primary" onclick="exportReturns()">
                <i class="fas fa-download me-2"></i>Export Returns
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-undo fa-2x mb-2 text-warning"></i>
                        <h3 class="mb-1" id="totalReturns">0</h3>
                        <p class="mb-0">Total Returns</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2 text-primary"></i>
                        <h3 class="mb-1" id="pendingReturns">0</h3>
                        <p class="mb-0">Pending Returns</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                        <h3 class="mb-1" id="completedReturns">0</h3>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-times-circle fa-2x mb-2 text-danger"></i>
                        <h3 class="mb-1" id="rejectedReturns">0</h3>
                        <p class="mb-0">Rejected</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Search Returns</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Order number, customer...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="filterReturns()">
                            <option value="">All Status</option>
                            <option value="requested">Requested</option>
                            <option value="pickup_scheduled">Pickup Scheduled</option>
                            <option value="collected">Collected</option>
                            <option value="received">Received</option>
                            <option value="processed">Processed</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="dateFilter" onchange="filterReturns()">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
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
                    <div class="col-md-3 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-fill" onclick="searchReturns()">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Returns Table -->
        <div class="card">
            <div class="card-body">
                <div class="loading text-center" id="tableLoading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading returns...</p>
                </div>
                
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover" id="returnsTable" style="display: none;">
                        <thead>
                            <tr>
                                <th>Return ID</th>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Photo</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>AWB</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="returnsTableBody">
                            <!-- Returns will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Returns pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination will be loaded here -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Return Details Modal -->
    <div class="modal fade" id="returnDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Return Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="returnDetailsBody">
                    <!-- Return details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="processReturn('approve')">
                        <i class="fas fa-check me-2"></i>Approve Return
                    </button>
                    <button type="button" class="btn btn-danger" onclick="processReturn('reject')">
                        <i class="fas fa-times me-2"></i>Reject Return
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
                    <h5 class="modal-title">Update Return Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm">
                    <input type="hidden" id="updateReturnId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Return ID</label>
                            <input type="text" class="form-control" id="updateReturnNumber" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="currentReturnStatus" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" id="newReturnStatus" required>
                                <option value="">Select Status</option>
                                <option value="requested">Requested</option>
                                <option value="pickup_scheduled">Pickup Scheduled</option>
                                <option value="collected">Collected</option>
                                <option value="received">Received</option>
                                <option value="processed">Processed</option>
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

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Return Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="enlargedPhoto" src="" alt="Return Photo" class="img-fluid" style="max-height: 500px;">
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
        let currentReturnId = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadReturns();
            loadStats();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('updateStatusForm').addEventListener('submit', handleStatusUpdate);
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchReturns();
                }
            });
        }

        // Load returns with pagination and filters
        async function loadReturns() {
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: 'get_returns',
                    page: currentPage,
                    per_page: itemsPerPage,
                    ...currentFilters
                });
                
                const response = await fetch(`api/manage-returns.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    displayReturns(data.returns);
                    updatePagination(data.pagination);
                    document.getElementById('returnsTable').style.display = 'table';
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading returns:', error);
                showAlert('Error loading returns', 'danger');
            } finally {
                showLoading(false);
            }
        }

        // Display returns in table
        function displayReturns(returns) {
            const tbody = document.getElementById('returnsTableBody');
            tbody.innerHTML = '';
            
            returns.forEach(returnItem => {
                const row = document.createElement('tr');
                
                // Customer info
                const customerHtml = returnItem.customer_name ? `
                    <div class="d-flex align-items-center">
                        <div class="customer-avatar placeholder me-2" style="width:30px;height:30px;border-radius:50%;background:#007bff;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;">
                            ${returnItem.customer_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.875rem;">${returnItem.customer_name}</div>
                            <small class="text-muted">${returnItem.customer_email}</small>
                        </div>
                    </div>
                ` : `<div class="text-muted"><i class="fas fa-user-slash me-1"></i>Guest</div>`;
                
                // Items summary
                const itemsHtml = returnItem.items ? returnItem.items.map(item => 
                    `<div class="return-item">${item.product_name} ${item.size ? `(${item.size})` : ''} x${item.quantity}</div>`
                ).join('') : '<small class="text-muted">No items</small>';
                
                // Photo
                const photoHtml = returnItem.photo_path ? 
                    `<img src="../${returnItem.photo_path}" alt="Return Photo" class="return-photo" onclick="enlargePhoto('../${returnItem.photo_path}')">` : 
                    '<span class="text-muted">No photo</span>';
                
                row.innerHTML = `
                    <td>
                        <div class="fw-bold text-primary">#${returnItem.id}</div>
                        ${returnItem.shiprocket_return_id ? `<small class="text-muted">SR: ${returnItem.shiprocket_return_id}</small>` : ''}
                    </td>
                    <td>
                        <div class="fw-bold">${returnItem.order_number}</div>
                        <small class="text-muted">₹${parseFloat(returnItem.order_amount).toFixed(2)}</small>
                    </td>
                    <td>${customerHtml}</td>
                    <td style="max-width: 200px;">${itemsHtml}</td>
                    <td>${photoHtml}</td>
                    <td>
                        <small class="text-muted">${returnItem.return_reason || 'Not specified'}</small>
                    </td>
                    <td>
                        <span class="status-badge status-${returnItem.return_status}">
                            ${returnItem.return_status.replace('_', ' ')}
                        </span>
                    </td>
                    <td>
                        ${returnItem.return_awb ? 
                            `<div class="fw-bold">${returnItem.return_awb}</div>` : 
                            '<span class="text-muted">Pending</span>'
                        }
                    </td>
                    <td>
                        <div>${new Date(returnItem.created_at).toLocaleDateString()}</div>
                        <small class="text-muted">${new Date(returnItem.created_at).toLocaleTimeString()}</small>
                    </td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewReturn(${returnItem.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn btn-edit" onclick="updateReturnStatus(${returnItem.id}, '${returnItem.return_status}')" title="Update Status">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Enlarge photo
        function enlargePhoto(photoPath) {
            document.getElementById('enlargedPhoto').src = photoPath;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }

        // View return details
        async function viewReturn(returnId) {
            currentReturnId = returnId;
            try {
                const response = await fetch(`api/manage-returns.php?action=get_return&id=${returnId}`);
                const data = await response.json();
                
                if (data.success) {
                    displayReturnDetails(data.return);
                    new bootstrap.Modal(document.getElementById('returnDetailsModal')).show();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading return details:', error);
                showAlert('Error loading return details', 'danger');
            }
        }
        
        function updateModalButtons(returnStatus, returnId) {
            // Find the modal footer
            const modalFooter = document.querySelector('#returnDetailsModal .modal-footer');
            if (!modalFooter) return;
            
            let buttonsHtml = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
            
            // Handle empty or null status as 'requested'
            const status = returnStatus || 'requested';
            console.log('Processing status:', status); // Debug log
            
            switch(status.toLowerCase()) {
                case '':
                case 'requested':
                    // Show both approve and reject buttons
                    buttonsHtml += `
                        <button type="button" class="btn btn-success" onclick="processReturn('approve')">
                            <i class="fas fa-check me-2"></i>Approve Return
                        </button>
                        <button type="button" class="btn btn-danger" onclick="processReturn('reject')">
                            <i class="fas fa-times me-2"></i>Reject Return
                        </button>
                    `;
                    break;
                    
                case 'pickup_scheduled':
                    buttonsHtml += `
                        <button type="button" class="btn btn-primary" onclick="updateReturnStatus(${returnId}, 'pickup_scheduled')">
                            <i class="fas fa-edit me-2"></i>Update Status
                        </button>
                    `;
                    break;
                    
                case 'rejected':
                    buttonsHtml += `
                        <span class="btn btn-danger disabled">
                            <i class="fas fa-times-circle me-2"></i>Return Rejected
                        </span>
                    `;
                    break;
                    
                case 'processed':
                    buttonsHtml += `
                        <span class="btn btn-success disabled">
                            <i class="fas fa-check-circle me-2"></i>Return Completed
                        </span>
                    `;
                    break;
                    
                default:
                    // For other statuses, show update button
                    buttonsHtml += `
                        <button type="button" class="btn btn-primary" onclick="updateReturnStatus(${returnId}, '${status}')">
                            <i class="fas fa-edit me-2"></i>Update Status
                        </button>
                    `;
                    break;
            }
            
            modalFooter.innerHTML = buttonsHtml;
        }


        // Display return details
        function displayReturnDetails(returnData) {
            // Build items table
            const itemsHtml = returnData.items ? returnData.items.map(item => `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${item.size || 'N/A'}</td>
                    <td>${item.quantity}</td>
                    <td>₹${parseFloat(item.product_price).toFixed(2)}</td>
                </tr>
            `).join('') : '<tr><td colspan="4">No items found</td></tr>';
        
            // Return photo display
            const photoHtml = returnData.photo_path 
                ? `<img src="../${returnData.photo_path}" alt="Return Photo" class="img-fluid" style="max-height: 200px; cursor: pointer;" onclick="enlargePhoto('../${returnData.photo_path}')">` 
                : '<p class="text-muted">No photo uploaded</p>';
        
            // Ensure status has fallback
            const returnStatus = returnData.return_status || 'requested';
            console.log('Return Status:', returnStatus); // Debug log
        
            // Build HTML details
            const detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Return Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Return ID:</strong></td><td>#${returnData.id}</td></tr>
                            <tr><td><strong>Order Number:</strong></td><td>${returnData.order_number}</td></tr>
                            <tr><td><strong>Status:</strong></td>
                                <td><span class="status-badge status-${returnStatus}">${returnStatus.replace('_', ' ')}</span></td></tr>
                            <tr><td><strong>AWB Code:</strong></td><td>${returnData.return_awb || 'Not assigned'}</td></tr>
                            <tr><td><strong>Reason:</strong></td><td>${returnData.return_reason || 'Not specified'}</td></tr>
                            <tr><td><strong>Date:</strong></td><td>${new Date(returnData.created_at).toLocaleString()}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        ${returnData.customer_name ? `
                            <p><strong>Name:</strong> ${returnData.customer_name}<br>
                               <strong>Email:</strong> ${returnData.customer_email}</p>
                        ` : '<p class="text-muted">Guest Order</p>'}
                        
                        <h6 class="mt-3">Return Photo</h6>
                        ${photoHtml}
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Returned Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Size</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        
            // Inject into modal body
            document.getElementById('returnDetailsBody').innerHTML = detailsHtml;
        
            // Update buttons based on actual status and ID
            updateModalButtons(returnStatus, returnData.id);
        }


        // Process return (approve/reject)
        async function processReturn(action) {
            if (!currentReturnId) {
                console.error('No current return ID');
                return;
            }
            
            const confirmMessage = action === 'approve' ? 
                'Are you sure you want to approve this return?' : 
                'Are you sure you want to reject this return? This will send an email notification to the customer.';
            
            if (!confirm(confirmMessage)) return;
            
            console.log(`Processing return: ${currentReturnId} with action: ${action}`);
            
            try {
                const formData = new FormData();
                formData.append('action', 'process_return');
                formData.append('return_id', currentReturnId);
                formData.append('process_action', action);
                
                console.log('FormData contents:', {
                    action: 'process_return',
                    return_id: currentReturnId,
                    process_action: action
                });
                
                const response = await fetch('api/manage-returns.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was not JSON:', responseText);
                    showAlert(`Server error: Invalid response format. Check console for details.`, 'danger');
                    return;
                }
                
                console.log('Parsed response:', data);
                
                if (data.success) {
                    const successMessage = action === 'approve' ? 
                        'Return approved successfully! Pickup will be scheduled.' :
                        'Return rejected successfully! Customer has been notified via email.';
                    showAlert(successMessage, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('returnDetailsModal')).hide();
                    loadReturns();
                    loadStats();
                } else {
                    console.error('API returned error:', data.message);
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error processing return:', error);
                showAlert('Network error processing return. Check console for details.', 'danger');
            }
        }

        // Update return status
        function updateReturnStatus(returnId, currentStatus) {
            document.getElementById('updateReturnId').value = returnId;
            document.getElementById('updateReturnNumber').value = '#' + returnId;
            document.getElementById('currentReturnStatus').value = currentStatus.replace('_', ' ').toUpperCase();
            document.getElementById('newReturnStatus').value = '';
            document.getElementById('statusNotes').value = '';
            
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Handle status update
        async function handleStatusUpdate(e) {
            e.preventDefault();
            
            const returnId = document.getElementById('updateReturnId').value;
            const newStatus = document.getElementById('newReturnStatus').value;
            const notes = document.getElementById('statusNotes').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('return_id', returnId);
                formData.append('status', newStatus);
                formData.append('notes', notes);
                
                const response = await fetch('api/manage-returns.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Return status updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('updateStatusModal')).hide();
                    loadReturns();
                    loadStats();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showAlert('Error updating return status', 'danger');
            }
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('api/manage-returns.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalReturns').textContent = data.stats.total_returns;
                    document.getElementById('pendingReturns').textContent = data.stats.pending_returns;
                    document.getElementById('completedReturns').textContent = data.stats.completed_returns;
                    document.getElementById('rejectedReturns').textContent = data.stats.rejected_returns;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Search, filter, pagination functions (similar to orders.php)
        function searchReturns() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            if (searchTerm) {
                currentFilters.search = searchTerm;
            } else {
                delete currentFilters.search;
            }
            currentPage = 1;
            loadReturns();
        }

        function filterReturns() {
            const status = document.getElementById('statusFilter').value;
            const dateRange = document.getElementById('dateFilter').value;
            
            currentFilters = {};
            if (status) currentFilters.status = status;
            if (dateRange) currentFilters.date_range = dateRange;
            
            currentPage = 1;
            loadReturns();
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            currentFilters = {};
            currentPage = 1;
            loadReturns();
        }

        function changeItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1;
            loadReturns();
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
                loadReturns();
            }
        }

        function exportReturns() {
            const params = new URLSearchParams({
                action: 'export_returns',
                ...currentFilters
            });
            window.open(`api/manage-returns.php?${params}`, '_blank');
        }

        function showLoading(show) {
            const loading = document.getElementById('tableLoading');
            const table = document.getElementById('returnsTable');
            
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