<?php
// admin/products.php - Product Management Interface
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
    <title>Product Management - Velona Admin</title>
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
                <h1 class="header-title mb-1">Product Management</h1>
                <p class="text-muted">Manage your product catalog and inventory</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus me-2"></i>Add New Product
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-box fa-2x mb-2"></i>
                        <h3 class="mb-1" id="totalProducts">0</h3>
                        <p class="mb-0">Total Products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="activeProducts">0</h3>
                        <p class="mb-0">Active Products</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="lowStockProducts">0</h3>
                        <p class="mb-0">Low Stock</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-times-circle fa-2x mb-2"></i>
                        <h3 class="mb-1" id="outOfStockProducts">0</h3>
                        <p class="mb-0">Out of Stock</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Search Products</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search by name...">
                            <button class="btn btn-outline-secondary" type="button" onclick="searchProducts()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="categoryFilter" onchange="filterProducts()">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="statusFilter" onchange="filterProducts()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Show</label>
                        <select class="form-select" id="itemsPerPage" onchange="changeItemsPerPage()">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-body">
                <div class="loading" id="tableLoading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading products...</p>
                </div>
                
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table table-hover" id="productsTable" style="display: none;">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <!-- Products will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Products pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination will be loaded here -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (₹) *</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock_quantity" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Care Instructions</label>
                            <textarea class="form-control" name="care_instructions" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Available Sizes</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="XS" id="sizeXS">
                                        <label class="form-check-label" for="sizeXS">XS</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="S" id="sizeS">
                                        <label class="form-check-label" for="sizeS">S</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="M" id="sizeM">
                                        <label class="form-check-label" for="sizeM">M</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="L" id="sizeL">
                                        <label class="form-check-label" for="sizeL">L</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="XL" id="sizeXL">
                                        <label class="form-check-label" for="sizeXL">XL</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sizes[]" value="XXL" id="sizeXXL">
                                        <label class="form-check-label" for="sizeXXL">XXL</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" class="form-control" name="low_stock_threshold" value="10" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="featured" id="featured">
                                    <label class="form-check-label" for="featured">
                                        Featured Product
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Product Images (Max 5)</label>
                            <input type="file" class="form-control" name="images[]" multiple accept="image/*" id="productImages">
                            <div class="form-text">Upload up to 5 images. First image will be primary.</div>
                            <div id="imagePreview" class="mt-3 d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2"></span>
                            Add Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" id="editProductId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" id="editName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category_id" id="editCategory" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (₹) *</label>
                                <input type="number" class="form-control" name="price" id="editPrice" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock_quantity" id="editStock" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Care Instructions</label>
                            <textarea class="form-control" name="care_instructions" id="editCareInstructions" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Available Sizes</label>
                                <div class="d-flex flex-wrap gap-2" id="editSizes">
                                    <!-- Sizes checkboxes will be populated here -->
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editStatus">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="out_of_stock">Out of Stock</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Low Stock Threshold</label>
                                <input type="number" class="form-control" name="low_stock_threshold" id="editLowStockThreshold" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="featured" id="editFeatured">
                                    <label class="form-check-label" for="editFeatured">
                                        Featured Product
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Images</label>
                            <div id="currentImages" class="d-flex flex-wrap gap-2 mb-3">
                                <!-- Current images will be shown here -->
                            </div>
                            
                            <label class="form-label">Add New Images</label>
                            <input type="file" class="form-control" name="new_images[]" multiple accept="image/*" id="editProductImages">
                            <div class="form-text">Upload new images to add. Existing images won't be affected.</div>
                            <div id="editImagePreview" class="mt-3 d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2"></span>
                            Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Stock Update Modal -->
    <div class="modal fade" id="stockUpdateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="stockUpdateForm">
                    <input type="hidden" id="stockProductId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="stockProductName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" class="form-control" id="currentStock" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Stock Quantity</label>
                            <input type="number" class="form-control" id="newStock" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
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
            console.log('Page loaded, initializing...');
            loadCategories();
            loadProducts();
            loadStats();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('productImages').addEventListener('change', function(e) {
                previewImages(e.target.files, 'imagePreview');
            });
            
            document.getElementById('editProductImages').addEventListener('change', function(e) {
                previewImages(e.target.files, 'editImagePreview');
            });
            
            document.getElementById('addProductForm').addEventListener('submit', handleAddProduct);
            document.getElementById('editProductForm').addEventListener('submit', handleEditProduct);
            document.getElementById('stockUpdateForm').addEventListener('submit', handleStockUpdate);
            
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchProducts();
                }
            });
        }

        async function loadCategories() {
            console.log('Loading categories...');
            try {
                const response = await fetch('api/manage-products.php?action=get_categories');
                console.log('Categories response:', response);
                const data = await response.json();
                console.log('Categories data:', data);
                
                if (data.success) {
                    const categorySelects = document.querySelectorAll('select[name="category_id"], #categoryFilter');
                    
                    categorySelects.forEach(select => {
                        const isFilter = select.id === 'categoryFilter';
                        select.innerHTML = isFilter ? '<option value="">All Categories</option>' : '<option value="">Select Category</option>';
                        
                        data.categories.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.name;
                            select.appendChild(option);
                        });
                    });
                    console.log('Categories loaded successfully');
                } else {
                    console.error('Categories loading failed:', data.message);
                    showAlert(data.message || 'Failed to load categories', 'danger');
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showAlert('Error loading categories: ' + error.message, 'danger');
            }
        }

        async function loadProducts() {
            console.log('Loading products...');
            showLoading(true);
            
            try {
                const params = new URLSearchParams({
                    action: 'get_products',
                    page: currentPage,
                    per_page: itemsPerPage,
                    ...currentFilters
                    });
                
                console.log('Fetching products with params:', params.toString());
                const response = await fetch(`api/manage-products.php?${params}`);
                console.log('Products response:', response);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('Products data:', data);
                
                if (data.success) {
                    displayProducts(data.products);
                    updatePagination(data.pagination);
                    document.getElementById('productsTable').style.display = 'table';
                    console.log('Products loaded successfully');
                } else {
                    console.error('Products loading failed:', data.message);
                    showAlert(data.message || 'Failed to load products', 'danger');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                showAlert('Error loading products: ' + error.message, 'danger');
            } finally {
                showLoading(false);
            }
        }

        function displayProducts(products) {
            console.log('Displaying products:', products);
            const tbody = document.getElementById('productsTableBody');
            tbody.innerHTML = '';
            
            if (!products || products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No products found</td></tr>';
                return;
            }
            
            products.forEach(product => {
                const row = document.createElement('tr');
                const productNameEscaped = product.name.replace(/'/g, "\\'").replace(/"/g, '\\"');
                
                row.innerHTML = `
                    <td>
                        <img src="${product.primary_image || '../uploads/products/default.jpg'}" 
                             alt="${product.name}" class="product-image"
                             onerror="this.src='../uploads/products/default.jpg'">
                    </td>
                    <td>
                        <div class="fw-bold">${product.name}</div>
                        <small class="text-muted">#${product.id}</small>
                    </td>
                    <td><span class="badge bg-secondary">${product.category_name}</span></td>
                    <td><strong>₹${parseFloat(product.price).toFixed(2)}</strong></td>
                    <td>
                        <span class="${product.stock_quantity <= product.low_stock_threshold ? 'stock-low' : 'stock-good'}">
                            ${product.stock_quantity}
                        </span>
                        <button class="btn btn-sm btn-outline-primary ms-1" onclick="openStockModal(${product.id}, '${productNameEscaped}', ${product.stock_quantity})">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <td>
                        <span class="status-badge status-${product.status}">
                            ${product.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </td>
                    <td>
                        <button class="action-btn btn-view" onclick="viewProduct(${product.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn btn-edit" onclick="editProduct(${product.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="action-btn btn-delete" onclick="deleteProduct(${product.id}, '${productNameEscaped}')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updatePagination(pagination) {
            currentPage = pagination.current_page;
            totalPages = pagination.total_pages;
            
            const paginationElement = document.getElementById('pagination');
            paginationElement.innerHTML = '';
            
            if (totalPages <= 1) {
                return; // No pagination needed
            }
            
            // Previous button
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
            paginationElement.appendChild(prevLi);
            
            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                const firstLi = document.createElement('li');
                firstLi.className = 'page-item';
                firstLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(1)">1</a>`;
                paginationElement.appendChild(firstLi);
                
                if (startPage > 2) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'page-item disabled';
                    dotsLi.innerHTML = `<span class="page-link">...</span>`;
                    paginationElement.appendChild(dotsLi);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageLi = document.createElement('li');
                pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
                pageLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i}</a>`;
                paginationElement.appendChild(pageLi);
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'page-item disabled';
                    dotsLi.innerHTML = `<span class="page-link">...</span>`;
                    paginationElement.appendChild(dotsLi);
                }
                
                const lastLi = document.createElement('li');
                lastLi.className = 'page-item';
                lastLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${totalPages})">${totalPages}</a>`;
                paginationElement.appendChild(lastLi);
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
                loadProducts();
            }
        }

        function changeItemsPerPage() {
            itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
            currentPage = 1;
            loadProducts();
        }

        function searchProducts() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            if (searchTerm) {
                currentFilters.search = searchTerm;
            } else {
                delete currentFilters.search;
            }
            currentPage = 1;
            loadProducts();
        }

        function filterProducts() {
            const category = document.getElementById('categoryFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            currentFilters = {};
            if (category) currentFilters.category_id = category;
            if (status) currentFilters.status = status;
            
            currentPage = 1;
            loadProducts();
        }

        async function loadStats() {
            console.log('Loading stats...');
            try {
                const response = await fetch('api/manage-products.php?action=get_stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalProducts').textContent = data.stats.total_products || 0;
                    document.getElementById('activeProducts').textContent = data.stats.active_products || 0;
                    document.getElementById('lowStockProducts').textContent = data.stats.low_stock_products || 0;
                    document.getElementById('outOfStockProducts').textContent = data.stats.out_of_stock_products || 0;
                    console.log('Stats loaded successfully');
                } else {
                    console.error('Stats loading failed:', data.message);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        function previewImages(files, containerId) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            Array.from(files).forEach((file, index) => {
                if (index < 5) { // Max 5 images
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'img-thumbnail';
                        img.style.width = '100px';
                        img.style.height = '100px';
                        img.style.objectFit = 'cover';
                        container.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        async function handleAddProduct(e) {
            e.preventDefault();
            console.log('Adding product...');
            
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'add_product');
                
                const response = await fetch('api/manage-products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Add product response:', data);
                
                if (data.success) {
                    showAlert('Product added successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                    form.reset();
                    document.getElementById('imagePreview').innerHTML = '';
                    loadProducts();
                    loadStats();
                } else {
                    showAlert(data.message || 'Failed to add product', 'danger');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                showAlert('Error adding product: ' + error.message, 'danger');
            } finally {
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        }

        async function editProduct(productId) {
            console.log('Editing product:', productId);
            try {
                const response = await fetch(`api/manage-products.php?action=get_product&id=${productId}`);
                const data = await response.json();
                console.log('Edit product data:', data);
                
                if (data.success) {
                    const product = data.product;
                    
                    document.getElementById('editProductId').value = product.id;
                    document.getElementById('editName').value = product.name || '';
                    document.getElementById('editCategory').value = product.category_id || '';
                    document.getElementById('editPrice').value = product.price || '';
                    document.getElementById('editStock').value = product.stock_quantity || '';
                    document.getElementById('editDescription').value = product.description || '';
                    document.getElementById('editCareInstructions').value = product.care_instructions || '';
                    document.getElementById('editStatus').value = product.status || 'active';
                    document.getElementById('editLowStockThreshold').value = product.low_stock_threshold || '';
                    document.getElementById('editFeatured').checked = product.featured == 1;
                    
                    // Populate sizes
                    const sizesContainer = document.getElementById('editSizes');
                    const sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
                    let productSizes = [];
                    
                    try {
                        productSizes = product.sizes ? (typeof product.sizes === 'string' ? JSON.parse(product.sizes) : product.sizes) : [];
                    } catch (e) {
                        console.error('Error parsing product sizes:', e);
                        productSizes = [];
                    }
                    
                    sizesContainer.innerHTML = '';
                    sizes.forEach(size => {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input" type="checkbox" name="sizes[]" value="${size}" 
                                   id="editSize${size}" ${productSizes.includes(size) ? 'checked' : ''}>
                            <label class="form-check-label" for="editSize${size}">${size}</label>
                        `;
                        sizesContainer.appendChild(div);
                    });
                    
                    // Show current images
                    const currentImagesContainer = document.getElementById('currentImages');
                    currentImagesContainer.innerHTML = '';
                    
                    if (product.images && product.images.length > 0) {
                        product.images.forEach((image, index) => {
                            const div = document.createElement('div');
                            div.className = 'position-relative';
                            div.innerHTML = `
                                <img src="${image.image_url}" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                        onclick="removeImage(${image.id || index})" style="padding: 2px 6px;">
                                    <i class="fas fa-times"></i>
                                </button>
                                ${image.is_primary ? '<span class="badge bg-primary position-absolute bottom-0 start-0">Primary</span>' : ''}
                            `;
                            currentImagesContainer.appendChild(div);
                        });
                    }
                    
                    new bootstrap.Modal(document.getElementById('editProductModal')).show();
                } else {
                    showAlert(data.message || 'Failed to load product', 'danger');
                }
            } catch (error) {
                console.error('Error loading product:', error);
                showAlert('Error loading product details: ' + error.message, 'danger');
            }
        }

        async function handleEditProduct(e) {
            e.preventDefault();
            console.log('Updating product...');
            
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            
            try {
                const formData = new FormData(form);
                formData.append('action', 'update_product');
                
                const response = await fetch('api/manage-products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Update product response:', data);
                
                if (data.success) {
                    showAlert('Product updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editProductModal')).hide();
                    loadProducts();
                    loadStats();
                } else {
                    showAlert(data.message || 'Failed to update product', 'danger');
                }
            } catch (error) {
                console.error('Error updating product:', error);
                showAlert('Error updating product: ' + error.message, 'danger');
            } finally {
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        }

        function viewProduct(productId) {
            window.open(`../shop/product.php?id=${productId}`, '_blank');
        }

        async function deleteProduct(productId, productName) {
            if (!confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                return;
            }
            
            console.log('Deleting product:', productId);
            try {
                const formData = new FormData();
                formData.append('action', 'delete_product');
                formData.append('product_id', productId);
                
                const response = await fetch('api/manage-products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Delete product response:', data);
                
                if (data.success) {
                    showAlert('Product deleted successfully!', 'success');
                    loadProducts();
                    loadStats();
                } else {
                    showAlert(data.message || 'Failed to delete product', 'danger');
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                showAlert('Error deleting product: ' + error.message, 'danger');
            }
        }

        function openStockModal(productId, productName, currentStock) {
            document.getElementById('stockProductId').value = productId;
            document.getElementById('stockProductName').value = productName;
            document.getElementById('currentStock').value = currentStock;
            document.getElementById('newStock').value = currentStock;
            
            new bootstrap.Modal(document.getElementById('stockUpdateModal')).show();
        }

        async function handleStockUpdate(e) {
            e.preventDefault();
            console.log('Updating stock...');
            
            const productId = document.getElementById('stockProductId').value;
            const newStock = document.getElementById('newStock').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_stock');
                formData.append('product_id', productId);
                formData.append('stock_quantity', newStock);
                
                const response = await fetch('api/manage-products.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Stock update response:', data);
                
                if (data.success) {
                    showAlert('Stock updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('stockUpdateModal')).hide();
                    loadProducts();
                    loadStats();
                } else {
                    showAlert(data.message || 'Failed to update stock', 'danger');
                }
            } catch (error) {
                console.error('Error updating stock:', error);
                showAlert('Error updating stock: ' + error.message, 'danger');
            }
        }

        function removeImage(imageId) {
            console.log('Remove image:', imageId);
            showAlert('Image removal feature coming soon', 'info');
        }

        function showLoading(show) {
            const loading = document.getElementById('tableLoading');
            const table = document.getElementById('productsTable');
            
            if (show) {
                loading.style.display = 'block';
                table.style.display = 'none';
            } else {
                loading.style.display = 'none';
                table.style.display = 'table';
            }
        }

        function showAlert(message, type) {
            console.log('Showing alert:', message, type);
            
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

        // Bulk actions (placeholder functions for future enhancement)
        function selectAllProducts() {
            console.log('Select all products');
        }

        function bulkUpdateStatus() {
            console.log('Bulk update status');
        }

        function bulkDelete() {
            console.log('Bulk delete');
        }

        function exportProducts() {
            console.log('Export products');
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