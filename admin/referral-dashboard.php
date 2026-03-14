<?php
// admin/referral-dashboard.php - Referral Dashboard (Previously admin.php)
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
// NO authentication check - admin.php already handled it
require_once 'admin-session.php';
// ONLY authentication check - Gateway control
requireAdminAuth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Dashboard - Bluefifth Admin</title>
    <link href="admin-styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<style>
/* Filter panel specific styles */
.users-section .card-header h5 {
    color: white;
    font-weight: 600;
}

.users-section .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

#filter-results {
    font-size: 0.9rem;
    font-weight: 500;
    color: #667eea;
}

.btn.me-2 {
    margin-right: 0.5rem;
}

/* Responsive adjustments for filter panel */
@media (max-width: 768px) {
    .users-section .row .col-md-4,
    .users-section .row .col-md-3,
    .users-section .row .col-md-2 {
        margin-bottom: 1rem;
    }
    
    .users-section .btn-sm {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .users-section .me-2 {
        margin-right: 0;
    }
}
.rounded-1{
    border-radius: 18px;
}
</style>
<body>
    <!-- Sidebar -->
    <?php include 'admin-navbar.php'; ?>
    
    <div class="main-content" id="adminContainer">
        <!-- Header -->
        <div class="admin-header">
            <h1>🚀 Referral Dashboard</h1>
            <p>Complete overview of referral users and system performance</p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number" id="total-users">-</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div class="stat-number" id="total-purchases">-</div>
                <div class="stat-label">Total Purchases</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number" id="total-sales">-</div>
                <div class="stat-label">Total Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎁</div>
                <div class="stat-number" id="total-points">-</div>
                <div class="stat-label">Points Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💸</div>
                <div class="stat-number" id="total-paid">-</div>
                <div class="stat-label">Money Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" id="pending-claims">-</div>
                <div class="stat-label">Pending Claims</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Monthly Performance Chart -->
            <div class="dashboard-section">
                <div class="section-header">
                    <span style="font-size: 1.5rem;">📊</span>
                    <h3>Monthly Performance</h3>
                </div>
                <div class="chart-container" id="monthly-chart">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        Loading chart data...
                    </div>
                </div>
            </div>

            <!-- Pending Claims -->
            <div class="dashboard-section">
                <div class="section-header">
                    <span style="font-size: 1.5rem;">💰</span>
                    <h3>Pending Claims</h3>
                </div>
                <div style="max-height: 400px; overflow-x: auto; overflow-x: auto;" >
                    <table class="claims-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="claims-tbody">
                            <tr>
                                <td colspan="4" class="loading">
                                    <div class="loading-spinner"></div>
                                    Loading claims...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
<!-- All Users Section -->
<div class="dashboard-section users-section">
    <div class="section-header">
        <span style="font-size: 1.5rem;">🌟</span>
        <h3>All Users - Complete Overview</h3>
    </div>
    
    <!-- Filter Panel -->
    <div class="card mb-4 rounded-1">
        <div class="card-header">
            <h5 class="mb-0">Search & Filter Users</h5>
        </div>
        <div class="card-body rounded-1">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="search-input" class="form-label">Search Users</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Search by name, email, mobile..." onkeyup="filterUsers()">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status-filter" class="form-label">Status</label>
                    <select id="status-filter" class="form-control" onchange="filterUsers()">
                        <option value="">All Users</option>
                        <option value="active">Active Users</option>
                        <option value="inactive">New Users</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="payment-filter" class="form-label">Payment Info</label>
                    <select id="payment-filter" class="form-control" onchange="filterUsers()">
                        <option value="">All</option>
                        <option value="complete">Complete Info</option>
                        <option value="incomplete">Incomplete Info</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="sort-select" class="form-label">Sort By</label>
                    <select id="sort-select" class="form-control" onchange="sortUsers()">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="name">Name A-Z</option>
                        <option value="earnings">Highest Earnings</option>
                        <option value="points">Most Points</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <button class="btn btn-secondary btn-sm me-2" onclick="clearFilters()">
                        Clear Filters
                    </button>
                    <span id="filter-results" class="text-muted"></span>
                </div>
            </div>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <!-- Your existing table code remains here -->
        <!-- All Users Section -->
        <div class="dashboard-section users-section">
            <div class="section-header">
                <span style="font-size: 1.5rem;">🌟</span>
                <h3>All Users - Complete Overview</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="users-table">
                    <!-- Update the existing table header in the HTML -->
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Wallet Points</th>
                            <th>Total Earned</th>
                            <th>Total Claimed</th>
                            <th>Referral Code</th>
                            <th>Visits</th>
                            <th>Sales</th>
                            <th>Mobile Number</th> <!-- NEW -->
                            <th>UPI ID</th>         <!-- NEW -->
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <tr>
                            <td colspan="8" class="loading">
                                <div class="loading-spinner"></div>
                                Loading users...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="refreshDashboard()" title="Refresh Dashboard">
        🔄
    </button>

    <script>
        let dashboardData = null;

        // Load dashboard data
        // In your existing loadDashboard function
        async function loadDashboard() {
            try {
                const response = await fetch('api/get-stats.php');
                const result = await response.json();
                
                if (result.success) {
                    dashboardData = result.data;
                    updateDashboard();
                    // Clear filters when new data loads
                    clearFilters();
                } else {
                    console.error('Failed to load dashboard:', result.message);
                    showError('Failed to load dashboard data');
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
                showError('Network error loading dashboard');
            }
        }

        // Update dashboard UI
        function updateDashboard() {
            if (!dashboardData) return;

            // Update stats overview
            document.getElementById('total-users').textContent = dashboardData.total_stats.total_users || 0;
            document.getElementById('total-purchases').textContent = dashboardData.total_stats.total_purchases || 0;
            document.getElementById('total-sales').textContent = '₹' + (dashboardData.total_stats.total_sales || 0).toLocaleString();
            document.getElementById('total-points').textContent = '₹' + (dashboardData.total_stats.total_points_earned || 0).toLocaleString();
            document.getElementById('total-paid').textContent = '₹' + (dashboardData.total_stats.total_money_paid || 0).toLocaleString();
            document.getElementById('pending-claims').textContent = dashboardData.pending_claims.length;

            // Update users table
            updateUsersTable();
            
            // Update claims table
            updateClaimsTable();
            
            // Update monthly chart
            updateMonthlyChart();
        }

        // Update users table
        function updateUsersTable() {
        const tbody = document.getElementById('users-tbody');
        
        if (!dashboardData.all_users || dashboardData.all_users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: #666;">No users found</td></tr>';
            return;
        }
    
        tbody.innerHTML = dashboardData.all_users.map(user => {
            const totalPoints = (parseFloat(user.points) || 0) + (parseFloat(user.pending_points) || 0);
            const avatarHtml = user.profile_image 
                ? `<img src="${user.profile_image}" alt="Avatar" class="user-avatar">`
                : `<div class="user-avatar placeholder">${user.name.charAt(0).toUpperCase()}</div>`;
            
            const status = totalPoints > 0 ? 'active' : 'inactive';
            const statusText = totalPoints > 0 ? 'Active' : 'New User';
            
            return `
                <tr>
                    <td>
                        <div class="user-info">
                            ${avatarHtml}
                            <div class="user-details">
                                <h4>${user.name}</h4>
                                <p>${user.email}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong>₹${totalPoints.toLocaleString()}</strong>
                        ${user.pending_points > 0 ? `<br><small style="color: #856404;">₹${parseFloat(user.pending_points).toLocaleString()} pending</small>` : ''}
                    </td>
                    <td>₹${(parseFloat(user.total_earned) || 0).toLocaleString()}</td>
                    <td>₹${(parseFloat(user.total_claimed) || 0).toLocaleString()}</td>
                    <td>
                        ${user.referral_code ? `<code>${user.referral_code}</code>` : '<em>No referrals yet</em>'}
                    </td>
                    <td>${parseInt(user.visit_count) || 0}</td>
                    <td>${parseInt(user.purchase_count) || 0}</td>
                    <td>
                        <strong>${user.mobile_number || '<em style="color:#999;">Not provided</em>'}</strong>
                    </td>
                    <td>
                        <code style="font-size:0.9em;">${user.upi_id || '<em style="color:#999;">Not provided</em>'}</code>
                    </td>
                    <td>
                        <span class="status-badge status-${status}">${statusText}</span>
                    </td>
                </tr>
            `;
        }).join('');
    }

        // Update claims table
        function updateClaimsTable() {
            const tbody = document.getElementById('claims-tbody');
            
            if (!dashboardData.pending_claims || dashboardData.pending_claims.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #666;">No pending claims</td></tr>';
                return;
            }

            tbody.innerHTML = dashboardData.pending_claims.map(claim => {
                const avatarHtml = claim.profile_image 
                    ? `<img src="${claim.profile_image}" alt="Avatar" class="user-avatar">`
                    : `<div class="user-avatar placeholder">${claim.name.charAt(0).toUpperCase()}</div>`;
                
                return `
                    <tr>
                        <td>
                            <div class="user-info">
                                ${avatarHtml}
                                <div class="user-details">
                                    <h4>${claim.name}</h4>
                                    <p>${claim.email}</p>
                                </div>
                            </div>
                        </td>
                        <td><strong>₹${parseFloat(claim.points_claimed).toLocaleString()}</strong></td>
                        <td>${new Date(claim.created_at).toLocaleDateString()}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-approve" onclick="processClaim(${claim.id}, 'approve')">
                                    ✅ Approve
                                </button>
                                <button class="btn btn-reject" onclick="processClaim(${claim.id}, 'reject')">
                                    ❌ Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Update monthly chart (placeholder)
        function updateMonthlyChart() {
            const chartContainer = document.getElementById('monthly-chart');
            
            if (!dashboardData.monthly_stats || dashboardData.monthly_stats.length === 0) {
                chartContainer.innerHTML = '<div style="color: #666;">📊 No monthly data available yet</div>';
                return;
            }

            // Simple text-based chart for now
            let chartHtml = '<div style="text-align: left; padding: 1rem;">';
            dashboardData.monthly_stats.forEach(stat => {
                const monthName = stat.purchase_month == 1 ? 'Month 1 (10%)' : `Month ${stat.purchase_month} (5%)`;
                chartHtml += `
                    <div style="margin-bottom: 1rem; padding: 0.5rem; background: #f8f9fa; border-radius: 5px;">
                        <strong>${monthName}</strong><br>
                        <small>Purchases: ${stat.purchase_count} | Sales: ₹${parseFloat(stat.total_sales).toLocaleString()}</small>
                    </div>
                `;
            });
            chartHtml += '</div>';
            
            chartContainer.innerHTML = chartHtml;
        }

        // Process claim function
        async function processClaim(claimId, action) {
            const actionText = action === 'approve' ? 'approve' : 'reject';
            
            if (!confirm(`Are you sure you want to ${actionText} this claim?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('claim_id', claimId);
                formData.append('action', action);
                formData.append('admin_notes', `${actionText.charAt(0).toUpperCase() + actionText.slice(1)}d on ${new Date().toLocaleString()}`);

                const response = await fetch('api/process-claim.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const responseText = await response.text();
                if (!responseText.trim()) {
                    throw new Error('Empty response from server');
                }
                
                const result = JSON.parse(responseText);

                if (result.success) {
                    const message = result.message || `Claim ${actionText}d successfully!`;
                    const emailStatus = result.email_sent ? ' (Email sent!)' : ' (Email failed)';
                    alert(`✅ ${message}${emailStatus}`);
                    loadDashboard();
                } else {
                    const errorMessage = result.message || `Failed to ${actionText} claim`;
                    alert(`❌ ${errorMessage}`);
                }

            } catch (error) {
                console.error(`Error ${actionText}ing claim:`, error);
                alert(`❌ Network error ${actionText}ing claim: ${error.message}`);
            }
        }

        // Refresh dashboard
        function refreshDashboard() {
            loadDashboard();
        }

        // Show error
        function showError(message) {
            alert('❌ ' + message);
        }

        // Auto-refresh every 30 seconds
        setInterval(loadDashboard, 30000);

        // Load dashboard on page load
        window.addEventListener('load', loadDashboard);
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
        // Global variables for filtering
let allUsersData = [];
let filteredUsersData = [];

// Updated updateUsersTable function
function updateUsersTable() {
    const tbody = document.getElementById('users-tbody');
    
    if (!dashboardData.all_users || dashboardData.all_users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: #666;">No users found</td></tr>';
        return;
    }

    // Store all users data globally
    allUsersData = [...dashboardData.all_users];
    filteredUsersData = [...allUsersData];
    
    renderUsersTable(filteredUsersData);
    updateFilterResults(filteredUsersData.length, allUsersData.length);
}

// New function to render users table
function renderUsersTable(users) {
    const tbody = document.getElementById('users-tbody');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: #666;">No users match the current filters</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => {
        const totalPoints = (parseFloat(user.points) || 0) + (parseFloat(user.pending_points) || 0);
        const avatarHtml = user.profile_image 
            ? `<img src="${user.profile_image}" alt="Avatar" class="user-avatar">`
            : `<div class="user-avatar placeholder">${user.name.charAt(0).toUpperCase()}</div>`;
        
        const status = totalPoints > 0 ? 'active' : 'inactive';
        const statusText = totalPoints > 0 ? 'Active' : 'New User';
        
        return `
            <tr>
                <td>
                    <div class="user-info">
                        ${avatarHtml}
                        <div class="user-details">
                            <h4>${user.name}</h4>
                            <p>${user.email}</p>
                        </div>
                    </div>
                </td>
                <td>
                    <strong>₹${totalPoints.toLocaleString()}</strong>
                    ${user.pending_points > 0 ? `<br><small style="color: #856404;">₹${parseFloat(user.pending_points).toLocaleString()} pending</small>` : ''}
                </td>
                <td>₹${(parseFloat(user.total_earned) || 0).toLocaleString()}</td>
                <td>₹${(parseFloat(user.total_claimed) || 0).toLocaleString()}</td>
                <td>
                    ${user.referral_code ? `<code>${user.referral_code}</code>` : '<em>No referrals yet</em>'}
                </td>
                <td>${parseInt(user.visit_count) || 0}</td>
                <td>${parseInt(user.purchase_count) || 0}</td>
                <td>
                    <strong>${user.mobile_number || '<em style="color:#999;">Not provided</em>'}</strong>
                </td>
                <td>
                    <code style="font-size:0.9em;">${user.upi_id || '<em style="color:#999;">Not provided</em>'}</code>
                </td>
                <td>
                    <span class="status-badge status-${status}">${statusText}</span>
                </td>
            </tr>
        `;
    }).join('');
}

// Filter function
function filterUsers() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
    const statusFilter = document.getElementById('status-filter').value;
    const paymentFilter = document.getElementById('payment-filter').value;
    
    filteredUsersData = allUsersData.filter(user => {
        // Search filter
        const matchesSearch = !searchTerm || 
            user.name.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm) ||
            (user.mobile_number && user.mobile_number.includes(searchTerm)) ||
            (user.upi_id && user.upi_id.toLowerCase().includes(searchTerm));
        
        // Status filter
        const totalPoints = (parseFloat(user.points) || 0) + (parseFloat(user.pending_points) || 0);
        const userStatus = totalPoints > 0 ? 'active' : 'inactive';
        const matchesStatus = !statusFilter || userStatus === statusFilter;
        
        // Payment info filter
        const hasCompletePaymentInfo = user.mobile_number && user.upi_id;
        const matchesPayment = !paymentFilter || 
            (paymentFilter === 'complete' && hasCompletePaymentInfo) ||
            (paymentFilter === 'incomplete' && !hasCompletePaymentInfo);
        
        return matchesSearch && matchesStatus && matchesPayment;
    });
    
    renderUsersTable(filteredUsersData);
    updateFilterResults(filteredUsersData.length, allUsersData.length);
}

// Sort function
function sortUsers() {
    const sortType = document.getElementById('sort-select').value;
    
    filteredUsersData.sort((a, b) => {
        switch(sortType) {
            case 'newest':
                return new Date(b.created_at) - new Date(a.created_at);
            case 'oldest':
                return new Date(a.created_at) - new Date(b.created_at);
            case 'name':
                return a.name.localeCompare(b.name);
            case 'earnings':
                const aEarnings = parseFloat(a.total_earned) || 0;
                const bEarnings = parseFloat(b.total_earned) || 0;
                return bEarnings - aEarnings;
            case 'points':
                const aPoints = (parseFloat(a.points) || 0) + (parseFloat(a.pending_points) || 0);
                const bPoints = (parseFloat(b.points) || 0) + (parseFloat(b.pending_points) || 0);
                return bPoints - aPoints;
            default:
                return 0;
        }
    });
    
    renderUsersTable(filteredUsersData);
}

// Clear filters function
function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('payment-filter').value = '';
    document.getElementById('sort-select').value = 'newest';
    
    filteredUsersData = [...allUsersData];
    renderUsersTable(filteredUsersData);
    updateFilterResults(filteredUsersData.length, allUsersData.length);
}

// Update filter results text
function updateFilterResults(filtered, total) {
    const resultsElement = document.getElementById('filter-results');
    if (filtered === total) {
        resultsElement.textContent = `Showing all ${total} users`;
    } else {
        resultsElement.textContent = `Showing ${filtered} of ${total} users`;
    }
}
        </script>

</body>
</html>