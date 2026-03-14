/**
 * shop.js - Complete Shop Management System
 * Handles search, filters, product interactions, and shop functionality
 * Compatible with search.php, category.php, and product pages
 * Version: 2.0 - Production Ready
 */

class VelonaShop {
    constructor() {
        this.searchApiEndpoint = 'api/search.php';
        this.cartApiEndpoint = 'api/cart.php';
        this.isSearching = false;
        this.isLoading = false;
        this.searchCache = {};
        this.searchTimeout = null;
        this.currentFilters = {};
        this.currentPage = 1;
        this.itemsPerPage = 12;
        this.isLoggedIn = window.velonaConfig?.isLoggedIn || false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initializeSearch();
        this.initializeFilters();
        this.initializeProductInteractions();
        this.setupKeyboardShortcuts();
        this.loadInitialData();
    }
    
    // ============================================================================
    // EVENT BINDING
    // ============================================================================
    
    bindEvents() {
        // Search functionality
        $(document).on('input', '#searchInput, .search-input', this.handleSearchInput.bind(this));
        $(document).on('keypress', '#searchInput, .search-input', this.handleSearchKeypress.bind(this));
        $(document).on('click', '.search-btn, .perform-search', this.handleSearchButton.bind(this));
        $(document).on('click', '.search-suggestion', this.handleSearchSuggestion.bind(this));
        
        // Filter functionality
        $(document).on('change', '#categorySelect, .category-filter', this.handleCategoryFilter.bind(this));
        $(document).on('change', '#sortSelect, .sort-filter', this.handleSortFilter.bind(this));
        $(document).on('change', '#limitSelect, .limit-filter', this.handleLimitFilter.bind(this));
        $(document).on('click', '.apply-price-filter', this.handlePriceFilter.bind(this));
        $(document).on('click', '.filter-tag button', this.handleRemoveFilter.bind(this));
        $(document).on('click', '.clear-filters-btn', this.handleClearFilters.bind(this));
        
        // Product interactions
        $(document).on('click', '.size-btn', this.handleSizeSelection.bind(this));
        $(document).on('click', '.color-btn', this.handleColorSelection.bind(this));
        $(document).on('click', '.product-image', this.handleImageClick.bind(this));
        $(document).on('mouseenter', '.product-card', this.handleProductHover.bind(this));
        
        // Modal and drawer controls
        $(document).on('click', '.toggle-search', this.toggleSearchModal.bind(this));
        $(document).on('click', '.mobile-filters-toggle', this.toggleMobileFilters.bind(this));
        $(document).on('click', '.quick-view-btn', this.handleQuickView.bind(this));
        
        // Pagination
        $(document).on('click', '.pagination-btn[data-page]', this.handlePagination.bind(this));
        
        // Wishlist and compare
        $(document).on('click', '.wishlist-btn', this.handleWishlist.bind(this));
        $(document).on('click', '.compare-btn', this.handleCompare.bind(this));
        
        // Newsletter
        $(document).on('submit', '.newsletter-form', this.handleNewsletterSubscription.bind(this));
        
        // Scroll handling
        $(window).on('scroll', this.throttle(this.handleScroll.bind(this), 100));
    }
    
    // ============================================================================
    // SEARCH FUNCTIONALITY
    // ============================================================================
    
    initializeSearch() {
        this.loadPopularSearches();
        this.setupSearchAutocomplete();
        this.loadSearchPreferences();
    }
    
    handleSearchInput(event) {
        const input = $(event.currentTarget);
        const query = input.val().trim();
        
        if (query.length < 2) {
            this.hideSearchSuggestions();
            return;
        }
        
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.loadSearchSuggestions(query);
        }, 300);
    }
    
    handleSearchKeypress(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.performSearch();
        }
        
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.navigateSearchSuggestions(event.key === 'ArrowDown' ? 1 : -1);
        }
        
        if (event.key === 'Escape') {
            this.hideSearchSuggestions();
            $(event.currentTarget).blur();
        }
    }
    
    handleSearchButton(event) {
        event.preventDefault();
        this.performSearch();
    }
    
    handleSearchSuggestion(event) {
        event.preventDefault();
        
        const suggestion = $(event.currentTarget);
        const query = suggestion.data('query') || suggestion.text().trim();
        
        $('#searchInput, .search-input').val(query);
        this.performSearch();
        this.hideSearchSuggestions();
    }
    
    async performSearch(customQuery = null) {
        if (this.isSearching) return;
        
        const query = customQuery || $('#searchInput, .search-input').val().trim();
        
        if (!query || query.length < 2) {
            this.showNotification('Please enter at least 2 characters', 'warning');
            return;
        }
        
        if (!window.location.pathname.includes('search.php')) {
            window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            return;
        }
        
        this.isSearching = true;
        this.showLoadingState();
        
        try {
            const params = new URLSearchParams({
                q: query,
                ...this.currentFilters,
                page: this.currentPage,
                limit: this.itemsPerPage
            });
            
            const response = await fetch(`${this.searchApiEndpoint}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.displaySearchResults(data);
                this.updateURLWithFilters(query);
                this.saveSearchToHistory(query);
            } else {
                this.showNotification(data.message || 'Search failed', 'error');
            }
            
        } catch (error) {
            console.error('Search error:', error);
            this.showNotification('Search failed. Please try again.', 'error');
        } finally {
            this.isSearching = false;
            this.hideLoadingState();
        }
    }
    
    async loadSearchSuggestions(query) {
        try {
            const response = await fetch(`${this.searchApiEndpoint}?action=suggestions&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.suggestions) {
                this.displaySearchSuggestions(data.suggestions, query);
            }
            
        } catch (error) {
            console.error('Search suggestions error:', error);
        }
    }
    
    displaySearchSuggestions(suggestions, query) {
        const container = $('#searchResults, .search-suggestions');
        
        if (!suggestions || suggestions.length === 0) {
            container.html('<div class="no-suggestions">No suggestions found</div>');
            return;
        }
        
        let html = '<div class="search-suggestions-list">';
        
        suggestions.forEach(suggestion => {
            const icon = this.getSuggestionIcon(suggestion.type);
            html += `
                <div class="search-suggestion" data-query="${suggestion.text}">
                    <i class="${icon}"></i>
                    <span class="suggestion-text">${this.highlightQuery(suggestion.text, query)}</span>
                    <span class="suggestion-type">${suggestion.category}</span>
                </div>
            `;
        });
        
        html += '</div>';
        container.html(html).show();
    }
    
    hideSearchSuggestions() {
        $('#searchResults, .search-suggestions').hide();
    }
    
    navigateSearchSuggestions(direction) {
        const suggestions = $('.search-suggestion');
        const current = $('.search-suggestion.active');
        
        if (suggestions.length === 0) return;
        
        let newIndex = 0;
        
        if (current.length > 0) {
            const currentIndex = suggestions.index(current);
            newIndex = currentIndex + direction;
            
            if (newIndex < 0) newIndex = suggestions.length - 1;
            if (newIndex >= suggestions.length) newIndex = 0;
        }
        
        suggestions.removeClass('active');
        const newSuggestion = suggestions.eq(newIndex).addClass('active');
        
        $('#searchInput, .search-input').val(newSuggestion.data('query'));
    }
    
    // ============================================================================
    // FILTER FUNCTIONALITY
    // ============================================================================
    
    initializeFilters() {
        this.loadFilterOptions();
        this.parseURLFilters();
    }
    
    handleCategoryFilter(event) {
        const select = $(event.currentTarget);
        const category = select.val();
        
        if (category) {
            this.currentFilters.category = category;
        } else {
            delete this.currentFilters.category;
        }
        
        this.currentPage = 1;
        this.applyFilters();
    }
    
    handleSortFilter(event) {
        const select = $(event.currentTarget);
        const sort = select.val();
        
        if (sort) {
            this.currentFilters.sort = sort;
        } else {
            delete this.currentFilters.sort;
        }
        
        this.applyFilters();
        this.saveSearchPreferences();
    }
    
    handleLimitFilter(event) {
        const select = $(event.currentTarget);
        const limit = parseInt(select.val());
        
        this.itemsPerPage = limit;
        this.currentPage = 1;
        
        this.applyFilters();
        this.saveSearchPreferences();
    }
    
    handlePriceFilter(event) {
        event.preventDefault();
        
        const minPrice = parseFloat($('#priceMin, .price-min').val()) || 0;
        const maxPrice = parseFloat($('#priceMax, .price-max').val()) || 0;
        
        if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
            this.showNotification('Minimum price cannot be greater than maximum price', 'warning');
            return;
        }
        
        if (minPrice > 0) {
            this.currentFilters.price_min = minPrice;
        } else {
            delete this.currentFilters.price_min;
        }
        
        if (maxPrice > 0) {
            this.currentFilters.price_max = maxPrice;
        } else {
            delete this.currentFilters.price_max;
        }
        
        this.currentPage = 1;
        this.applyFilters();
    }
    
    handleRemoveFilter(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const filterTag = button.closest('.filter-tag');
        const filterType = filterTag.data('filter-type');
        
        if (filterType) {
            delete this.currentFilters[filterType];
            this.currentPage = 1;
            this.applyFilters();
        }
    }
    
    handleClearFilters(event) {
        event.preventDefault();
        
        this.currentFilters = {};
        this.currentPage = 1;
        
        $('.category-filter, .sort-filter').val('');
        $('.price-min, .price-max').val('');
        
        this.applyFilters();
    }
    
    async applyFilters() {
        const currentQuery = this.getCurrentSearchQuery();
        
        if (currentQuery) {
            await this.performSearch(currentQuery);
        } else {
            this.updateURLWithFilters();
            window.location.reload();
        }
    }
    
    async loadFilterOptions() {
        try {
            const response = await fetch(`${this.searchApiEndpoint}?action=filters`);
            const data = await response.json();
            
            if (data.success) {
                this.updateFilterUI(data);
            }
            
        } catch (error) {
            console.error('Load filter options error:', error);
        }
    }
    
    updateFilterUI(filterData) {
        if (filterData.categories) {
            const categorySelect = $('#categorySelect, .category-filter');
            categorySelect.empty().append('<option value="">All Categories</option>');
            
            filterData.categories.forEach(category => {
                categorySelect.append(`
                    <option value="${category.slug}" ${this.currentFilters.category === category.slug ? 'selected' : ''}>
                        ${category.name} (${category.count})
                    </option>
                `);
            });
        }
        
        if (filterData.price_range) {
            $('.price-min').attr('max', filterData.price_range.max);
            $('.price-max').attr('max', filterData.price_range.max);
        }
    }
    
    // ============================================================================
    // PRODUCT INTERACTIONS
    // ============================================================================
    
    initializeProductInteractions() {
        this.setupProductImageLazyLoading();
        this.initializeProductQuickView();
        this.setupProductCompare();
    }
    
    handleSizeSelection(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const productCard = button.closest('.product-card, .product-details');
        
        button.siblings('.size-btn').removeClass('active');
        button.addClass('active');
        
        productCard.find('input[name="size"]').val(button.data('size'));
        
        this.updateProductPrice(productCard, button.data('size'));
        
        $(document).trigger('velona:shop:size_selected', {
            product_id: productCard.data('product-id'),
            size: button.data('size'),
            button: button[0]
        });
    }
    
    handleColorSelection(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const productCard = button.closest('.product-card, .product-details');
        
        button.siblings('.color-btn').removeClass('active');
        button.addClass('active');
        
        this.updateProductImage(productCard, button.data('color'));
        
        $(document).trigger('velona:shop:color_selected', {
            product_id: productCard.data('product-id'),
            color: button.data('color'),
            button: button[0]
        });
    }
    
    handleImageClick(event) {
        event.preventDefault();
        
        const image = $(event.currentTarget);
        const largeImageUrl = image.data('large-image') || image.attr('src');
        
        this.openImageLightbox(largeImageUrl, image.attr('alt'));
    }
    
    handleProductHover(event) {
        const productCard = $(event.currentTarget);
        const hoverImage = productCard.find('.hover-img');
        
        if (hoverImage.length && !hoverImage.attr('src')) {
            const hoverSrc = hoverImage.data('src');
            if (hoverSrc) {
                hoverImage.attr('src', hoverSrc);
            }
        }
    }
    
    handleQuickView(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const productId = button.data('product-id');
        
        this.loadQuickView(productId);
    }
    
    async loadQuickView(productId) {
        try {
            this.showLoadingModal('Loading product details...');
            
            const response = await fetch(`api/product.php?id=${productId}&action=quick_view`);
            const data = await response.json();
            
            if (data.success) {
                this.displayQuickViewModal(data.product);
            } else {
                this.showNotification('Failed to load product details', 'error');
            }
            
        } catch (error) {
            console.error('Quick view error:', error);
            this.showNotification('Failed to load product details', 'error');
        } finally {
            this.hideLoadingModal();
        }
    }
    
    displayQuickViewModal(product) {
        const modal = this.createQuickViewModal(product);
        $('body').append(modal);
        $('#quickViewModal').modal('show');
        
        $('#quickViewModal').on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
    
    createQuickViewModal(product) {
        return `
            <div class="modal fade" id="quickViewModal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${product.name}</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <img src="${product.primary_image}" alt="${product.name}" class="img-fluid">
                                </div>
                                <div class="col-md-6">
                                    <div class="product-details">
                                        <h4>${product.name}</h4>
                                        <p class="price">${product.formatted_price}</p>
                                        <p class="description">${product.description}</p>
                                        
                                        ${product.sizes && product.sizes.length > 0 ? `
                                            <div class="size-selection mb-3">
                                                <label>Size:</label>
                                                <div class="size-options">
                                                    ${product.sizes.map(size => `
                                                        <button class="btn btn-outline-secondary size-btn" data-size="${size}">${size}</button>
                                                    `).join('')}
                                                </div>
                                            </div>
                                        ` : ''}
                                        
                                        <div class="quick-actions">
                                            <button class="btn btn-primary add-to-cart-btn" 
                                                    data-product-id="${product.id}" 
                                                    data-product-name="${product.name}">
                                                <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                                            </button>
                                            <a href="product.php?id=${product.id}" class="btn btn-outline-primary ml-2">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // ============================================================================
    // PAGINATION
    // ============================================================================
    
    handlePagination(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const page = parseInt(button.data('page'));
        
        if (page && page !== this.currentPage) {
            this.currentPage = page;
            this.applyFilters();
            
            $('html, body').animate({
                scrollTop: $('.search-header, .page-header').offset()?.top || 0
            }, 500);
        }
    }
    
    // ============================================================================
    // MOBILE AND RESPONSIVE
    // ============================================================================
    
    toggleSearchModal() {
        $('#searchModal').modal('show');
        setTimeout(() => {
            $('#searchInput').focus();
        }, 500);
    }
    
    toggleMobileFilters() {
        $('#searchFilters, .mobile-filters').toggleClass('show');
    }
    
    // ============================================================================
    // WISHLIST AND COMPARE
    // ============================================================================
    
    handleWishlist(event) {
        event.preventDefault();
        
        if (!this.isLoggedIn) {
            this.showNotification('Please log in to add items to wishlist', 'warning');
            return;
        }
        
        const button = $(event.currentTarget);
        const productId = button.data('product-id');
        
        this.toggleWishlist(productId, button);
    }
    
    async toggleWishlist(productId, button) {
        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'toggle_wishlist');
            
            const response = await fetch('api/wishlist.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                button.toggleClass('active');
                const icon = button.find('i');
                
                if (data.added) {
                    icon.removeClass('far').addClass('fas');
                    this.showNotification('Added to wishlist', 'success');
                } else {
                    icon.removeClass('fas').addClass('far');
                    this.showNotification('Removed from wishlist', 'success');
                }
            }
            
        } catch (error) {
            console.error('Wishlist error:', error);
            this.showNotification('Failed to update wishlist', 'error');
        }
    }
    
    handleCompare(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const productId = button.data('product-id');
        
        this.toggleCompare(productId, button);
    }
    
    toggleCompare(productId, button) {
        let compareList = JSON.parse(localStorage.getItem('velona_compare') || '[]');
        const maxCompareItems = 4;
        
        const index = compareList.indexOf(productId);
        
        if (index > -1) {
            compareList.splice(index, 1);
            button.removeClass('active');
            this.showNotification('Removed from compare', 'success');
        } else {
            if (compareList.length >= maxCompareItems) {
                this.showNotification(`You can only compare ${maxCompareItems} products at once`, 'warning');
                return;
            }
            
            compareList.push(productId);
            button.addClass('active');
            this.showNotification('Added to compare', 'success');
        }
        
        localStorage.setItem('velona_compare', JSON.stringify(compareList));
        this.updateCompareCounter(compareList.length);
    }
    
    updateCompareCounter(count) {
        $('.compare-counter').text(count).toggle(count > 0);
    }
    
    // ============================================================================
    // NEWSLETTER AND USER INTERACTIONS
    // ============================================================================
    
    handleNewsletterSubscription(event) {
        event.preventDefault();
        
        const form = $(event.currentTarget);
        const email = form.find('input[type="email"]').val().trim();
        
        if (!email || !this.isValidEmail(email)) {
            this.showNotification('Please enter a valid email address', 'error');
            return;
        }
        
        this.subscribeToNewsletter(email, form);
    }
    
    async subscribeToNewsletter(email, form) {
        try {
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
            submitBtn.prop('disabled', true).text('Subscribing...');
            
            const response = await fetch('api/newsletter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Successfully subscribed to newsletter!', 'success');
                form[0].reset();
            } else {
                this.showNotification(data.message || 'Subscription failed', 'error');
            }
            
        } catch (error) {
            console.error('Newsletter subscription error:', error);
            this.showNotification('Subscription failed. Please try again.', 'error');
        } finally {
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', false).text('Subscribe');
        }
    }
    
    // ============================================================================
    // INFINITE SCROLL AND LAZY LOADING
    // ============================================================================
    
    handleScroll() {
        if (this.shouldLoadMoreProducts()) {
            this.loadMoreProducts();
        }
        
        this.lazyLoadImages();
        this.updateScrollProgress();
    }
    
    shouldLoadMoreProducts() {
        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const documentHeight = $(document).height();
        const scrollPercentage = (scrollTop + windowHeight) / documentHeight;
        
        return scrollPercentage > 0.8 && !this.isLoading && this.hasMoreProducts();
    }
    
    hasMoreProducts() {
        const currentProductCount = $('.product-card').length;
        const totalProducts = parseInt($('.total-products').text()) || 0;
        
        return currentProductCount < totalProducts;
    }
    
    async loadMoreProducts() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoadMoreIndicator();
        
        try {
            const nextPage = Math.floor($('.product-card').length / this.itemsPerPage) + 1;
            const query = this.getCurrentSearchQuery();
            
            const params = new URLSearchParams({
                q: query || '',
                ...this.currentFilters,
                page: nextPage,
                limit: this.itemsPerPage
            });
            
            const response = await fetch(`${this.searchApiEndpoint}?${params}`);
            const data = await response.json();
            
            if (data.success && data.products && data.products.length > 0) {
                this.appendProducts(data.products);
            }
            
        } catch (error) {
            console.error('Load more products error:', error);
        } finally {
            this.isLoading = false;
            this.hideLoadMoreIndicator();
        }
    }
    
    appendProducts(products) {
        const container = $('.products-grid, .product-list');
        
        products.forEach(product => {
            const productHtml = this.createProductCard(product);
            container.append(productHtml);
        });
        
        $(document).trigger('velona:shop:products_loaded', { products });
    }
    
    setupProductImageLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.dataset.src;
                        
                        if (src) {
                            img.src = src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    }
                });
            });
            
            $('.lazy-image').each(function() {
                imageObserver.observe(this);
            });
        }
    }
    
    lazyLoadImages() {
        $('.lazy-image:not(.loaded)').each(function() {
            const img = $(this);
            const rect = this.getBoundingClientRect();
            
            if (rect.top <= window.innerHeight + 100) {
                const src = img.data('src');
                if (src) {
                    img.attr('src', src).addClass('loaded');
                }
            }
        });
    }
    
    // ============================================================================
    // UI HELPERS AND UTILITIES
    // ============================================================================
    
    displaySearchResults(data) {
        const container = $('#productsGrid, .products-container');
        
        if (!data.products || data.products.length === 0) {
            this.displayNoResults(data.filters?.query || '');
            return;
        }
        
        this.updateResultsCount(data.pagination);
        
        container.empty();
        
        data.products.forEach(product => {
            const productHtml = this.createProductCard(product);
            container.append(productHtml);
        });
        
        this.updatePagination(data.pagination);
        this.updateFilterTags(data.filters);
        this.updateURLWithFilters(data.filters?.query);
    }
    
    createProductCard(product) {
        const sizes = product.sizes && product.sizes.length > 0 ? 
            product.sizes.slice(0, 4).map(size => 
                `<button class="btn btn-outline-secondary btn-sm size-btn" data-size="${size}">${size}</button>`
            ).join('') : '';
        
        return `
            <div class="col-12 col-md-6 col-lg-4 mb-4">
                <div class="product-card" data-product-id="${product.id}">
                    <a href="${product.url}" class="text-decoration-none text-dark">
                        <div class="image-container">
                            <img src="${product.primary_image}" 
                                 alt="${product.name}" 
                                 class="default-img img-fluid">
                            ${product.primary_image ? `
                                <img src="${product.primary_image}" 
                                     alt="${product.name}" 
                                     class="hover-img img-fluid">
                            ` : ''}
                        </div>
                        
                        <h5 class="product-title">${product.name}</h5>
                        <p class="product-price">${product.formatted_price}</p>
                        
                        <span class="badge badge-secondary mb-2">${product.category_name}</span>
                        
                        ${!product.in_stock ? '<span class="badge badge-danger">Out of Stock</span>' : ''}
                        ${product.stock_status === 'low_stock' ? '<span class="badge badge-warning">Low Stock</span>' : ''}
                    </a>
                    
                    ${sizes ? `
                        <div class="size-options">
                            <small class="size-label">Size:</small>
                            <div class="btn-group btn-group-sm" role="group">
                                ${sizes}
                            </div>
                    ` : ''}
                    
                    <div class="product-actions">
                        ${this.isLoggedIn ? `
                            <button class="add-to-cart-btn btn btn-primary" 
                                    data-product-id="${product.id}" 
                                    data-product-name="${product.name}"
                                    ${!product.in_stock ? 'disabled' : ''}>
                                ${!product.in_stock ? 'Out of Stock' : '<i class="fas fa-shopping-cart mr-2"></i>Add to Cart'}
                            </button>
                        ` : `
                            <button class="btn btn-primary login-required" 
                                    data-product-id="${product.id}">
                                <i class="fas fa-shopping-cart mr-2"></i>Login to Add
                            </button>
                        `}
                        
                        <button class="btn btn-outline-secondary wishlist-btn ml-2" 
                                data-product-id="${product.id}" 
                                title="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </button>
                        
                        <button class="btn btn-outline-secondary quick-view-btn ml-2" 
                                data-product-id="${product.id}" 
                                title="Quick View">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    displayNoResults(query) {
        const container = $('#productsGrid, .products-container');
        
        container.html(`
            <div class="col-12">
                <div class="no-results text-center py-5">
                    <i class="fas fa-search no-results-icon" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h3 class="no-results-title">No products found</h3>
                    <p class="no-results-text">
                        We couldn't find any products matching "<strong>${query}</strong>".
                        <br>Try different keywords or browse our categories.
                    </p>
                    
                    <div class="mt-4">
                        <h5>Popular searches:</h5>
                        <div class="d-flex flex-wrap justify-content-center">
                            <a href="search.php?q=shirt" class="suggestion-item">Shirts</a>
                            <a href="search.php?q=dress" class="suggestion-item">Dresses</a>
                            <a href="search.php?q=jeans" class="suggestion-item">Jeans</a>
                            <a href="search.php?q=jacket" class="suggestion-item">Jackets</a>
                            <a href="category.php" class="suggestion-item">View All Products</a>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    updateResultsCount(pagination) {
        if (!pagination) return;
        
        const countText = `<strong>${pagination.showing_from}</strong> to <strong>${pagination.showing_to}</strong> of <strong>${pagination.total_results}</strong> results`;
        $('.results-count').html(countText);
    }
    
    updatePagination(pagination) {
        if (!pagination || pagination.total_pages <= 1) {
            $('.pagination-wrapper').hide();
            return;
        }
        
        const container = $('.pagination-wrapper');
        let html = '';
        
        if (pagination.has_prev_page) {
            html += `<a href="#" class="pagination-btn" data-page="${pagination.current_page - 1}">
                        <i class="fas fa-chevron-left"></i> Previous
                     </a>`;
        }
        
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        if (startPage > 1) {
            html += `<a href="#" class="pagination-btn" data-page="1">1</a>`;
            if (startPage > 2) {
                html += `<span class="pagination-btn">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="pagination-btn ${i === pagination.current_page ? 'active' : ''}" 
                        data-page="${i}">${i}</a>`;
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                html += `<span class="pagination-btn">...</span>`;
            }
            html += `<a href="#" class="pagination-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
        }
        
        if (pagination.has_next_page) {
            html += `<a href="#" class="pagination-btn" data-page="${pagination.current_page + 1}">
                        Next <i class="fas fa-chevron-right"></i>
                     </a>`;
        }
        
        container.html(html).show();
    }
    
    updateFilterTags(filters) {
        const container = $('.filter-tags');
        let html = '';
        
        if (filters.category) {
            html += `<span class="filter-tag" data-filter-type="category">
                        Category: ${filters.category}
                        <button type="button">×</button>
                     </span>`;
        }
        
        if (filters.price_min) {
            html += `<span class="filter-tag" data-filter-type="price_min">
                        Min: ₹${filters.price_min}
                        <button type="button">×</button>
                     </span>`;
        }
        
        if (filters.price_max) {
            html += `<span class="filter-tag" data-filter-type="price_max">
                        Max: ₹${filters.price_max}
                        <button type="button">×</button>
                     </span>`;
        }
        
        if (filters.sort_by) {
            html += `<span class="filter-tag" data-filter-type="sort">
                        Sort: ${filters.sort_by}
                        <button type="button">×</button>
                     </span>`;
        }
        
        if (html) {
            html += `<button class="clear-filters-btn">
                        <i class="fas fa-times mr-1"></i>Clear All
                     </button>`;
        }
        
        container.html(html);
    }
    
    // ============================================================================
    // KEYBOARD SHORTCUTS AND ACCESSIBILITY
    // ============================================================================
    
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                this.toggleSearchModal();
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'f' && window.innerWidth < 768) {
                e.preventDefault();
                this.toggleMobileFilters();
            }
            
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                this.navigateProducts(e.key === 'ArrowRight' ? 1 : -1);
            }
        });
    }
    
    navigateProducts(direction) {
        const products = $('.product-card');
        const focused = $('.product-card:focus, .product-card.keyboard-focus');
        
        if (products.length === 0) return;
        
        let newIndex = 0;
        
        if (focused.length > 0) {
            const currentIndex = products.index(focused);
            newIndex = currentIndex + direction;
            
            if (newIndex < 0) newIndex = products.length - 1;
            if (newIndex >= products.length) newIndex = 0;
        }
        
        products.removeClass('keyboard-focus');
        products.eq(newIndex).addClass('keyboard-focus').focus();
    }
    
    // ============================================================================
    // LOADING STATES AND NOTIFICATIONS
    // ============================================================================
    
    showLoadingState() {
        $('#loadingState, .loading-state').removeClass('d-none').show();
        $('.products-container').addClass('loading');
    }
    
    hideLoadingState() {
        $('#loadingState, .loading-state').addClass('d-none').hide();
        $('.products-container').removeClass('loading');
    }
    
    showLoadMoreIndicator() {
        $('.load-more-indicator').show();
    }
    
    hideLoadMoreIndicator() {
        $('.load-more-indicator').hide();
    }
    
    showLoadingModal(message = 'Loading...') {
        const modal = $(`
            <div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" data-backdrop="static">
                <div class="modal-dialog modal-sm" role="document">
                    <div class="modal-content">
                        <div class="modal-body text-center py-4">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mb-0">${message}</p>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        modal.modal('show');
    }
    
    hideLoadingModal() {
        $('#loadingModal').modal('hide').remove();
    }
    
    showNotification(message, type = 'info') {
        $('.velona-notification').remove();
        
        const typeClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const notification = $(`
            <div class="alert ${typeClass} alert-dismissible fade show velona-notification" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, () => notification.remove());
        }, 5000);
    }
    
    // ============================================================================
    // UTILITY METHODS
    // ============================================================================
    
    getCurrentSearchQuery() {
        return $('#searchInput, .search-input').val().trim() || 
               new URLSearchParams(window.location.search).get('q') || '';
    }
    
    parseURLFilters() {
        const params = new URLSearchParams(window.location.search);
        
        this.currentFilters = {};
        
        if (params.get('category')) this.currentFilters.category = params.get('category');
        if (params.get('price_min')) this.currentFilters.price_min = parseFloat(params.get('price_min'));
        if (params.get('price_max')) this.currentFilters.price_max = parseFloat(params.get('price_max'));
        if (params.get('sort')) this.currentFilters.sort = params.get('sort');
        
        this.currentPage = parseInt(params.get('page')) || 1;
        this.itemsPerPage = parseInt(params.get('limit')) || 12;
    }
    
    updateURLWithFilters(query = null) {
        const url = new URL(window.location);
        const params = url.searchParams;
        
        if (query) {
            params.set('q', query);
        }
        
        Object.keys(this.currentFilters).forEach(key => {
            if (this.currentFilters[key]) {
                params.set(key, this.currentFilters[key]);
            } else {
                params.delete(key);
            }
        });
        
        if (this.currentPage > 1) {
            params.set('page', this.currentPage);
        } else {
            params.delete('page');
        }
        
        if (this.itemsPerPage !== 12) {
            params.set('limit', this.itemsPerPage);
        } else {
            params.delete('limit');
        }
        
        window.history.replaceState({}, '', url.toString());
    }
    
    saveSearchToHistory(query) {
        let history = JSON.parse(localStorage.getItem('velona_search_history') || '[]');
        
        history = history.filter(item => item !== query);
        history.unshift(query);
        history = history.slice(0, 10);
        
        localStorage.setItem('velona_search_history', JSON.stringify(history));
    }
    
    loadSearchPreferences() {
        try {
            const saved = localStorage.getItem('velona_search_preferences');
            if (saved) {
                const preferences = JSON.parse(saved);
                
                if (Date.now() - preferences.timestamp < 7 * 24 * 60 * 60 * 1000) {
                    if (preferences.sort && !$('#sortSelect').val()) {
                        $('#sortSelect').val(preferences.sort);
                        this.currentFilters.sort = preferences.sort;
                    }
                    if (preferences.itemsPerPage && this.itemsPerPage === 12) {
                        this.itemsPerPage = preferences.itemsPerPage;
                        $('#limitSelect').val(preferences.itemsPerPage);
                    }
                }
            }
        } catch (e) {
            console.warn('Could not load search preferences:', e);
        }
    }
    
    saveSearchPreferences() {
        const preferences = {
            sort: this.currentFilters.sort || '',
            itemsPerPage: this.itemsPerPage,
            timestamp: Date.now()
        };
        
        try {
            localStorage.setItem('velona_search_preferences', JSON.stringify(preferences));
        } catch (e) {
            console.warn('Could not save search preferences:', e);
        }
    }
    
    async loadPopularSearches() {
        try {
            const response = await fetch(`${this.searchApiEndpoint}?action=popular_searches`);
            const data = await response.json();
            
            if (data.success && data.popular_searches) {
                this.displayPopularSearches(data.popular_searches);
            }
        } catch (error) {
            console.error('Load popular searches error:', error);
        }
    }
    
    displayPopularSearches(searches) {
        const container = $('.popular-searches, .trending-searches');
        
        if (container.length && searches.length > 0) {
            let html = '<h6>Popular searches:</h6><div class="search-tags">';
            
            searches.forEach(search => {
                html += `<a href="search.php?q=${encodeURIComponent(search)}" class="search-tag">${search}</a>`;
            });
            
            html += '</div>';
            container.html(html);
        }
    }
    
    setupSearchAutocomplete() {
        let searchInput = $('#searchInput, .search-input');
        
        searchInput.on('focus', () => {
            const query = searchInput.val().trim();
            if (query.length >= 2) {
                this.loadSearchSuggestions(query);
            } else {
                this.displayRecentSearches();
            }
        });
        
        $(document).on('click', (e) => {
            if (!$(e.target).closest('.search-container, .search-suggestions').length) {
                this.hideSearchSuggestions();
            }
        });
    }
    
    displayRecentSearches() {
        const history = JSON.parse(localStorage.getItem('velona_search_history') || '[]');
        
        if (history.length > 0) {
            const suggestions = history.slice(0, 5).map(query => ({
                text: query,
                type: 'recent',
                category: 'recent'
            }));
            
            this.displaySearchSuggestions(suggestions, '');
        }
    }
    
    getSuggestionIcon(type) {
        const icons = {
            'product': 'fas fa-tag',
            'category': 'fas fa-folder',
            'search': 'fas fa-search',
            'recent': 'fas fa-history'
        };
        
        return icons[type] || 'fas fa-search';
    }
    
    highlightQuery(text, query) {
        if (!query) return text;
        
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }
    
    updateProductPrice(productCard, size) {
        const priceElement = productCard.find('.product-price');
        const basePrice = parseFloat(priceElement.data('base-price')) || 0;
        
        priceElement.text(`₹${basePrice.toFixed(2)}`);
    }
    
    updateProductImage(productCard, color) {
        const imageContainer = productCard.find('.image-container');
        const colorImage = imageContainer.find(`[data-color="${color}"]`);
        
        if (colorImage.length) {
            imageContainer.find('img').hide();
            colorImage.show();
        }
    }
    
    openImageLightbox(imageUrl, altText) {
        const lightbox = $(`
            <div class="image-lightbox" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                 background: rgba(0,0,0,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div class="lightbox-content" style="max-width: 90%; max-height: 90%; position: relative;">
                    <img src="${imageUrl}" alt="${altText}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    <button class="close-lightbox" style="position: absolute; top: -40px; right: 0; background: none; 
                            border: none; color: white; font-size: 30px; cursor: pointer;">×</button>
                </div>
            </div>
        `);
        
        $('body').append(lightbox);
        
        lightbox.on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('close-lightbox')) {
                $(this).fadeOut(300, () => $(this).remove());
            }
        });
        
        $(document).on('keydown.lightbox', function(e) {
            if (e.key === 'Escape') {
                lightbox.fadeOut(300, () => lightbox.remove());
                $(document).off('keydown.lightbox');
            }
        });
    }
    
    updateScrollProgress() {
        const scrollTop = $(window).scrollTop();
        const documentHeight = $(document).height() - $(window).height();
        const progress = (scrollTop / documentHeight) * 100;
        
        $('.scroll-progress-bar').css('width', progress + '%');
    }
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    loadInitialData() {
        this.parseURLFilters();
        this.loadPopularSearches();
        this.updateCompareCounter(JSON.parse(localStorage.getItem('velona_compare') || '[]').length);
    }
    
    initializeProductQuickView() {
        // Initialize quick view functionality
    }
    
    setupProductCompare() {
        // Initialize product comparison
        const compareList = JSON.parse(localStorage.getItem('velona_compare') || '[]');
        this.updateCompareCounter(compareList.length);
    }
    
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// ============================================================================
// INITIALIZATION AND GLOBAL SETUP
// ============================================================================

$(document).ready(function() {
    window.VelonaShop = new VelonaShop();
    window.shop = window.VelonaShop;
    
    $(document).on('velona:shop:size_selected', function(event, data) {
        console.log('Size selected:', data);
    });
    
    $(document).on('velona:shop:color_selected', function(event, data) {
        console.log('Color selected:', data);
    });
    
    $(document).on('velona:shop:products_loaded', function(event, data) {
        console.log('Products loaded:', data);
        window.VelonaShop.setupProductImageLazyLoading();
    });
    
    $('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();
    
    $('a[href*="#"]:not([href="#"])').click(function() {
        if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
            var target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 800);
                return false;
            }
        }
    });
});

// ============================================================================
// LEGACY SUPPORT FUNCTIONS
// ============================================================================

window.performSearch = function(query) {
    return window.VelonaShop.performSearch(query);
};

window.toggleSearchModal = function() {
    return window.VelonaShop.toggleSearchModal();
};

window.toggleMobileFilters = function() {
    return window.VelonaShop.toggleMobileFilters();
};

window.handleSearchKeypress = function(event) {
    return window.VelonaShop.handleSearchKeypress(event);
};

window.applySorting = function() {
    const sortValue = $('#sortSelect').val();
    window.VelonaShop.currentFilters.sort = sortValue;
    window.VelonaShop.applyFilters();
};

window.applyFilters = function() {
    return window.VelonaShop.applyFilters();
};

window.applyPriceFilter = function() {
    return window.VelonaShop.handlePriceFilter({ preventDefault: () => {} });
};

window.changeItemsPerPage = function() {
    const limit = parseInt($('#limitSelect').val());
    window.VelonaShop.itemsPerPage = limit;
    window.VelonaShop.currentPage = 1;
    window.VelonaShop.applyFilters();
};

window.removeFilter = function(filterType) {
    delete window.VelonaShop.currentFilters[filterType];
    window.VelonaShop.currentPage = 1;
    window.VelonaShop.applyFilters();
};

window.clearAllFilters = function() {
    window.VelonaShop.currentFilters = {};
    window.VelonaShop.currentPage = 1;
    
    $('.category-filter, .sort-filter').val('');
    $('.price-min, .price-max').val('');
    
    window.VelonaShop.applyFilters();
};

// ============================================================================
// ANALYTICS AND TRACKING
// ============================================================================

function trackShopEvent(action, data = {}) {
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            event_category: 'shop',
            ...data
        });
    }
    
    if (typeof fbq !== 'undefined') {
        switch (action) {
            case 'search':
                fbq('track', 'Search', {
                    search_string: data.query
                });
                break;
            case 'view_category':
                fbq('track', 'ViewContent', {
                    content_type: 'category',
                    content_name: data.category
                });
                break;
            case 'view_product':
                fbq('track', 'ViewContent', {
                    content_type: 'product',
                    content_ids: [data.product_id],
                    content_name: data.product_name
                });
                break;
        }
    }
    
    if (window.velonaAnalytics) {
        window.velonaAnalytics.track(action, data);
    }
}

// ============================================================================
// ERROR HANDLING AND PERFORMANCE
// ============================================================================

window.addEventListener('error', function(e) {
    console.error('Shop JS Error:', e.error);
    
    if (typeof gtag !== 'undefined') {
        gtag('event', 'exception', {
            description: e.error?.message || 'Unknown error',
            fatal: false
        });
    }
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled Promise Rejection:', e.reason);
    
    if (typeof gtag !== 'undefined') {
        gtag('event', 'exception', {
            description: e.reason?.message || 'Promise rejection',
            fatal: false
        });
    }
});

// ============================================================================
// ACCESSIBILITY ENHANCEMENTS
// ============================================================================

$(document).on('keydown', '.product-card', function(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).find('a').first()[0].click();
    }
});

function announceToScreenReader(message) {
    const announcement = $('<div>')
        .addClass('sr-only')
        .attr('aria-live', 'polite')
        .text(message);
    
    $('body').append(announcement);
    
    setTimeout(() => {
        announcement.remove();
    }, 1000);
}

$(document).on('shown.bs.modal', '.modal', function() {
    $(this).find('input, button, [tabindex]').first().focus();
});

$('body').prepend(`
    <a href="#main-content" class="sr-only sr-only-focusable skip-link">
        Skip to main content
    </a>
`);

// ============================================================================
// PERFORMANCE OPTIMIZATIONS
// ============================================================================

function preloadCriticalResources() {
    const criticalImages = [
        '../assets/images/logo.png',
        '../assets/images/default-product.jpg'
    ];
    
    criticalImages.forEach(src => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = src;
        document.head.appendChild(link);
    });
}

function optimizeImages() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    if (!img.hasAttribute('loading')) {
                        img.setAttribute('loading', 'lazy');
                    }
                    
                    observer.unobserve(img);
                }
            });
        });
        
        $('.product-card img').each(function() {
            imageObserver.observe(this);
        });
    }
}

function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction() {
        const context = this;
        const args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

$(document).ready(function() {
    preloadCriticalResources();
    optimizeImages();
    
    const optimizedResize = debounce(function() {
        if (window.innerWidth >= 768) {
            $('.mobile-filters').removeClass('show');
        }
    }, 250);
    
    $(window).on('resize', optimizedResize);
    
    $('body').addClass('shop-loaded');
    $('.page-loader, .initial-loader').fadeOut();
    
    $(document).trigger('velona:shop:ready');
    
    console.log('Velona Shop JS initialized successfully');
});

$(window).on('beforeunload', function() {
    if (window.VelonaShop) {
        window.VelonaShop.isSearching = false;
        window.VelonaShop.isLoading = false;
    }
    
    if (window.VelonaShop?.searchTimeout) {
        clearTimeout(window.VelonaShop.searchTimeout);
    }
});