<?php
// admin/categories.php - Category Management Interface
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
    <title>Category Management - Velona Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="admin-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'admin-navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title mb-1">Category Management</h1>
                <p class="text-muted">Organize your product categories</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
        </div>

        <!-- Categories Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categoriesTableBody">
                            <!-- Categories will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addCategoryForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">HSN Code</label>
                            <input type="text" class="form-control" name="hsn_code" placeholder="Enter HSN Code">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCategoryForm" enctype="multipart/form-data">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">HSN Code</label>
                            <input type="text" class="form-control" name="hsn_code" id="editHsnCode" placeholder="Enter HSN Code">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div id="currentImage" class="mb-2"></div>
                            <label class="form-label">New Image (Optional)</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editStatus">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" id="editSortOrder" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('addCategoryForm').addEventListener('submit', handleAddCategory);
            document.getElementById('editCategoryForm').addEventListener('submit', handleEditCategory);
        }

        // Load categories
        async function loadCategories() {
            try {
                const response = await fetch('api/manage-categories.php?action=get_categories');
                const data = await response.json();
                
                if (data.success) {
                    displayCategories(data.categories);
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showAlert('Error loading categories', 'danger');
            }
        }

        // Display categories in table
        function displayCategories(categories) {
            const tbody = document.getElementById('categoriesTableBody');
            tbody.innerHTML = '';
            
            categories.forEach(category => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <img src="${category.image || '/ecommerce-project/uploads/categories/default.jpg'}"
                            alt="${category.name}" class="category-image"
                            onerror="this.src='/ecommerce-project/uploads/categories/default.jpg'">
                    </td>
                    <td>
                        <div class="fw-bold">${category.name}</div>
                        <small class="text-muted">${category.slug}</small>
                    </td>
                    <td>${category.description || 'No description'}</td>
                    <td><span class="badge bg-info">${category.product_count || 0} products</span></td>
                    <td><span class="status-${category.status}">${category.status.toUpperCase()}</span></td>
                    <td>${category.sort_order}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editCategory(${category.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${category.id}, '${category.name}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Handle add category
        async function handleAddCategory(e) {
            e.preventDefault();
            
            try {
                const formData = new FormData(e.target);
                formData.append('action', 'add_category');
                
                const response = await fetch('api/manage-categories.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Category added successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
                    e.target.reset();
                    loadCategories();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error adding category:', error);
                showAlert('Error adding category', 'danger');
            }
        }

        // Edit category
        async function editCategory(categoryId) {
            try {
                const response = await fetch(`api/manage-categories.php?action=get_category&id=${categoryId}`);
                const data = await response.json();
                
                if (data.success) {
                    const category = data.category;
                    
                    document.getElementById('editCategoryId').value = category.id;
                    document.getElementById('editName').value = category.name;
                    document.getElementById('editDescription').value = category.description || '';
                    document.getElementById('editStatus').value = category.status;
                    document.getElementById('editSortOrder').value = category.sort_order;
                    
                    // Show current image
                    const currentImageDiv = document.getElementById('currentImage');
                    if (category.image) {
                        currentImageDiv.innerHTML = `<img src="${category.image}" class="category-image" alt="Current image">`;
                    } else {
                        currentImageDiv.innerHTML = '<p class="text-muted">No image uploaded</p>';
                    }
                    
                    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error loading category:', error);
                showAlert('Error loading category details', 'danger');
            }
        }

        // Handle edit category
        async function handleEditCategory(e) {
            e.preventDefault();
            
            try {
                const formData = new FormData(e.target);
                formData.append('action', 'update_category');
                
                const response = await fetch('api/manage-categories.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Category updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')).hide();
                    loadCategories();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error updating category:', error);
                showAlert('Error updating category', 'danger');
            }
        }

        // Delete category
        async function deleteCategory(categoryId, categoryName) {
            if (!confirm(`Are you sure you want to delete "${categoryName}"? This action cannot be undone.`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_category');
                formData.append('category_id', categoryId);
                
                const response = await fetch('api/manage-categories.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Category deleted successfully!', 'success');
                    loadCategories();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error deleting category:', error);
                showAlert('Error deleting category', 'danger');
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