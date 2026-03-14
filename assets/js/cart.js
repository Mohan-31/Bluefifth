/**
 * cart.js - Complete Cart Management System
 * Handles all cart operations, UI updates, and user interactions
 * Compatible with both logged-in users and guests
 */

class VelonaCart {
    constructor() {
        this.isLoggedIn = window.velonaConfig?.isLoggedIn || false;
        this.apiEndpoint = 'api/cart.php';
        this.isLoading = false;
        this.cartCache = null;
        this.updateTimeout = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadCartSummary();
        this.setupPeriodicSync();
        this.initializeQuantityControls();
        this.setupKeyboardShortcuts();
    }
    
    // ============================================================================
    // EVENT BINDING
    // ============================================================================
    
    bindEvents() {
        // Add to cart buttons
        $(document).on('click', '.add-to-cart-btn, .add-to-cart', this.handleAddToCart.bind(this));
        
        // Quantity controls
        $(document).on('click', '.quantity-btn', this.handleQuantityChange.bind(this));
        $(document).on('change', '.quantity-input', this.handleQuantityInput.bind(this));
        
        // Remove from cart
        $(document).on('click', '.remove-btn, .remove-from-cart', this.handleRemoveFromCart.bind(this));
        
        // Clear cart
        $(document).on('click', '.clear-cart-btn', this.handleClearCart.bind(this));
        
        // Size selection
        $(document).on('click', '.size-btn', this.handleSizeSelection.bind(this));
        
        // Cart drawer/modal
        $(document).on('click', '.cart-toggle', this.toggleCartDrawer.bind(this));
        $(document).on('click', '.cart-overlay', this.closeCartDrawer.bind(this));
        
        // Checkout button
        $(document).on('click', '.checkout-btn', this.handleCheckout.bind(this));
        
        // Continue shopping
        $(document).on('click', '.continue-shopping', this.closCart.bind(this));
        
        // Login for guests
        $(document).on('click', '.login-to-add-cart', this.promptLogin.bind(this));
        
        // Page visibility change (sync cart when user returns)
        $(document).on('visibilitychange', this.handleVisibilityChange.bind(this));
        
        // Before page unload (save guest cart)
        $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
    }
    
    // ============================================================================
    // CART OPERATIONS
    // ============================================================================
    
    async addToCart(productId, quantity = 1, size = null, options = {}) {
        if (this.isLoading) return;
        
        // Validate inputs
        if (!productId || quantity <= 0) {
            this.showNotification('Invalid product or quantity', 'error');
            return;
        }
        
        this.isLoading = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            if (size) formData.append('size', size);
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update cart UI
                this.updateCartBadge(data.cart_summary?.item_count || 0);
                this.updateCartSummary(data.cart_summary);
                
                // Show success message
                const productName = options.productName || 'Product';
                this.showNotification(`${productName} added to cart!`, 'success');
                
                // Update button state
                if (options.buttonElement) {
                    this.updateAddToCartButton(options.buttonElement, 'added');
                }
                
                // Trigger custom event
                this.triggerCartEvent('item_added', {
                    product_id: productId,
                    quantity: quantity,
                    size: size,
                    cart_summary: data.cart_summary
                });
                
                // Auto-open cart drawer if enabled
                if (options.showCartDrawer) {
                    setTimeout(() => this.openCartDrawer(), 500);
                }
                
            } else {
                this.showNotification(data.message || 'Failed to add item to cart', 'error');
            }
            
        } catch (error) {
            console.error('Add to cart error:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            this.isLoading = false;
        }
    }
    
    async updateQuantity(cartId, newQuantity, maxStock = null) {
        if (this.isLoading) return;
        
        // Validate quantity
        if (newQuantity < 0) newQuantity = 0;
        if (maxStock && newQuantity > maxStock) {
            this.showNotification(`Only ${maxStock} items available`, 'warning');
            return;
        }
        
        this.isLoading = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'update_cart');
            
            if (this.isLoggedIn) {
                formData.append('cart_item_id', cartId);
            } else {
                formData.append('cart_key', cartId);
            }
            
            formData.append('quantity', newQuantity);
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (newQuantity === 0) {
                    // Item was removed
                    this.removeCartItemFromDOM(cartId);
                    this.showNotification('Item removed from cart', 'success');
                } else {
                    // Item was updated
                    this.updateCartItemInDOM(cartId, newQuantity);
                    this.showNotification('Cart updated', 'success');
                }
                
                // Update cart summary
                this.updateCartBadge(data.cart_summary?.item_count || 0);
                this.updateCartSummary(data.cart_summary);
                
                // Check if cart is empty
                if ((data.cart_summary?.item_count || 0) === 0) {
                    this.showEmptyCartState();
                }
                
                // Trigger custom event
                this.triggerCartEvent('quantity_updated', {
                    cart_id: cartId,
                    new_quantity: newQuantity,
                    cart_summary: data.cart_summary
                });
                
            } else {
                this.showNotification(data.message || 'Failed to update cart', 'error');
            }
            
        } catch (error) {
            console.error('Update quantity error:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            this.isLoading = false;
        }
    }
    
    async removeFromCart(cartId) {
        if (this.isLoading) return;
        
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        this.isLoading = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            
            if (this.isLoggedIn) {
                formData.append('cart_item_id', cartId);
            } else {
                formData.append('cart_key', cartId);
            }
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove item from DOM
                this.removeCartItemFromDOM(cartId);
                
                // Update cart summary
                this.updateCartBadge(data.cart_summary?.item_count || 0);
                this.updateCartSummary(data.cart_summary);
                
                this.showNotification('Item removed from cart', 'success');
                
                // Check if cart is empty
                if ((data.cart_summary?.item_count || 0) === 0) {
                    this.showEmptyCartState();
                }
                
                // Trigger custom event
                this.triggerCartEvent('item_removed', {
                    cart_id: cartId,
                    cart_summary: data.cart_summary
                });
                
            } else {
                this.showNotification(data.message || 'Failed to remove item', 'error');
            }
            
        } catch (error) {
            console.error('Remove from cart error:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            this.isLoading = false;
        }
    }
    
    async clearCart() {
        if (this.isLoading) return;
        
        if (!confirm('Are you sure you want to clear your entire cart?')) {
            return;
        }
        
        this.isLoading = true;
        
        try {
            const formData = new FormData();
            formData.append('action', 'clear_cart');
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear cart UI
                this.clearCartDOM();
                this.updateCartBadge(0);
                this.showEmptyCartState();
                
                this.showNotification('Cart cleared successfully', 'success');
                
                // Trigger custom event
                this.triggerCartEvent('cart_cleared', {});
                
            } else {
                this.showNotification(data.message || 'Failed to clear cart', 'error');
            }
            
        } catch (error) {
            console.error('Clear cart error:', error);
            this.showNotification('Network error. Please try again.', 'error');
        } finally {
            this.isLoading = false;
        }
    }
    
    async loadCartSummary() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=summary`);
            const data = await response.json();
            
            if (data.success) {
                this.cartCache = data.cart_summary;
                this.updateCartBadge(data.cart_summary?.item_count || 0);
                this.updateCartSummary(data.cart_summary);
            }
            
        } catch (error) {
            console.error('Load cart summary error:', error);
        }
    }
    
    async validateCart() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=validate_cart`);
            const data = await response.json();
            
            if (data.success && !data.valid && data.issues?.length > 0) {
                this.handleCartValidationIssues(data.issues);
            }
            
        } catch (error) {
            console.error('Cart validation error:', error);
        }
    }
    
    // ============================================================================
    // EVENT HANDLERS
    // ============================================================================
    
    handleAddToCart(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const productId = button.data('product-id') || button.attr('data-product-id');
        const productName = button.data('product-name') || button.attr('data-product-name');
        const quantity = parseInt(button.data('quantity') || 1);
        
        // Get selected size
        const productCard = button.closest('.product-card, .product-item, .product-details');
        const selectedSizeBtn = productCard.find('.size-btn.active');
        const size = selectedSizeBtn.length ? selectedSizeBtn.data('size') : null;
        
        // Check if user needs to login
        if (!this.isLoggedIn && button.hasClass('login-required')) {
            this.promptLogin();
            return;
        }
        
        // Update button state
        this.updateAddToCartButton(button[0], 'loading');
        
        // Add to cart
        this.addToCart(productId, quantity, size, {
            productName: productName,
            buttonElement: button[0],
            showCartDrawer: button.data('show-drawer') === true
        });
    }
    
    handleQuantityChange(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const isIncrease = button.text().includes('+') || button.hasClass('increase');
        const quantityInput = button.siblings('.quantity-input');
        const currentQuantity = parseInt(quantityInput.val()) || 1;
        const maxStock = parseInt(quantityInput.attr('max')) || 999;
        const cartId = button.closest('tr, .cart-item').data('cart-id');
        
        let newQuantity = isIncrease ? currentQuantity + 1 : currentQuantity - 1;
        
        this.updateQuantity(cartId, newQuantity, maxStock);
    }
    
    handleQuantityInput(event) {
        const input = $(event.currentTarget);
        const newQuantity = parseInt(input.val()) || 1;
        const maxStock = parseInt(input.attr('max')) || 999;
        const cartId = input.closest('tr, .cart-item').data('cart-id');
        
        // Debounce the update
        clearTimeout(this.updateTimeout);
        this.updateTimeout = setTimeout(() => {
            this.updateQuantity(cartId, newQuantity, maxStock);
        }, 500);
    }
    
    handleRemoveFromCart(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        const cartId = button.closest('tr, .cart-item').data('cart-id');
        
        this.removeFromCart(cartId);
    }
    
    handleClearCart(event) {
        event.preventDefault();
        this.clearCart();
    }
    
    handleSizeSelection(event) {
        event.preventDefault();
        
        const button = $(event.currentTarget);
        
        // Remove active class from siblings
        button.siblings('.size-btn').removeClass('active');
        
        // Add active class to clicked button
        button.addClass('active');
        
        // Trigger custom event
        this.triggerCartEvent('size_selected', {
            size: button.data('size'),
            button: button[0]
        });
    }
    
    handleCheckout(event) {
        event.preventDefault();
        
        if (!this.isLoggedIn) {
            this.promptLogin('You need to log in to proceed to checkout.');
            return;
        }
        
        // Validate cart first
        this.validateCart().then(() => {
            window.location.href = '../checkout.php';
        });
    }
    
    handleVisibilityChange() {
        if (!document.hidden && this.isLoggedIn) {
            // Sync cart when user returns to tab
            this.loadCartSummary();
        }
    }
    
    handleBeforeUnload() {
        // Save guest cart to localStorage as backup
        if (!this.isLoggedIn && this.cartCache) {
            try {
                localStorage.setItem('velona_guest_cart_backup', JSON.stringify({
                    cart: this.cartCache,
                    timestamp: Date.now()
                }));
            } catch (e) {
                console.warn('Could not save guest cart backup:', e);
            }
        }
    }
    
    // ============================================================================
    // UI UPDATES
    // ============================================================================
    
    updateCartBadge(itemCount) {
        const badge = $('#cartBadge, .cart-badge');
        
        if (itemCount > 0) {
            if (badge.length) {
                badge.text(itemCount).show();
            } else {
                // Create badge if it doesn't exist
                $('.fa-bag-shopping, .cart-icon').parent().append(
                    `<span class="position-absolute badge badge-danger" id="cartBadge" style="top: -8px; right: -8px; font-size: 0.7rem;">${itemCount}</span>`
                );
            }
        } else {
            badge.hide();
        }
        
        // Update cart counter in other places
        $('.cart-count').text(itemCount);
    }
    
    updateCartSummary(cartSummary) {
        if (!cartSummary) return;
        
        // Update subtotal
        $('#subtotalAmount, .subtotal-amount').text(`₹${cartSummary.total_amount.toFixed(2)}`);
        
        // Update shipping
        const shippingCost = cartSummary.shipping_cost || 0;
        $('#shippingAmount, .shipping-amount').text(
            shippingCost > 0 ? `₹${shippingCost.toFixed(2)}` : 'FREE'
        );
        
        // Update total
        const finalTotal = cartSummary.final_total || cartSummary.total_amount;
        $('#totalAmount, .total-amount').text(`₹${finalTotal.toFixed(2)} INR`);
        
        // Update item count in summary
        $('.summary-item-count').text(cartSummary.item_count);
        
        // Update free shipping progress
        this.updateFreeShippingProgress(cartSummary);
        
        // Cache the summary
        this.cartCache = cartSummary;
    }
    
    updateFreeShippingProgress(cartSummary) {
        const threshold = cartSummary.free_shipping_threshold || 1000;
        const current = cartSummary.total_amount || 0;
        const remaining = Math.max(0, threshold - current);
        const progress = Math.min(100, (current / threshold) * 100);
        
        // Update progress bar
        $('.free-shipping-bar').css('width', progress + '%');
        
        // Update message
        const promoSection = $('.promo-section');
        if (promoSection.length) {
            if (remaining > 0) {
                promoSection.html(`
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-truck mr-2"></i>
                            Add ₹${remaining.toFixed(2)} more for FREE shipping
                        </span>
                    </div>
                    <div class="free-shipping-progress">
                        <div class="free-shipping-bar" style="width: ${progress}%"></div>
                    </div>
                `);
            } else {
                promoSection.html(`
                    <i class="fas fa-check-circle mr-2"></i>
                    Congratulations! You qualify for FREE shipping
                `);
            }
        }
    }
    
    updateAddToCartButton(button, state) {
        const $button = $(button);
        const originalText = $button.data('original-text') || $button.text();
        
        if (!$button.data('original-text')) {
            $button.data('original-text', originalText);
        }
        
        switch (state) {
            case 'loading':
                $button.prop('disabled', true)
                       .html('<i class="fas fa-spinner fa-spin mr-2"></i>Adding...');
                break;
                
            case 'added':
                $button.prop('disabled', true)
                       .html('<i class="fas fa-check mr-2"></i>Added!')
                       .addClass('btn-success');
                
                setTimeout(() => {
                    $button.prop('disabled', false)
                           .html(originalText)
                           .removeClass('btn-success');
                }, 2000);
                break;
                
            case 'error':
                $button.prop('disabled', false)
                       .html(originalText);
                break;
                
            default:
                $button.prop('disabled', false)
                       .html(originalText);
        }
    }
    
    updateCartItemInDOM(cartId, newQuantity) {
        const row = $(`[data-cart-id="${cartId}"]`);
        if (row.length) {
            // Update quantity input
            row.find('.quantity-input').val(newQuantity);
            
            // Update item total if price is available
            const price = parseFloat(row.find('.product-variant:last-child').text().replace(/[₹,]/g, ''));
            if (price) {
                const itemTotal = newQuantity * price;
                row.find('.item-total').text(`₹${itemTotal.toFixed(2)}`);
            }
            
            // Highlight the updated row
            row.addClass('updated-item');
            setTimeout(() => row.removeClass('updated-item'), 2000);
        }
    }
    
    removeCartItemFromDOM(cartId) {
        const row = $(`[data-cart-id="${cartId}"]`);
        if (row.length) {
            row.fadeOut(300, function() {
                $(this).remove();
            });
        }
    }
    
    clearCartDOM() {
        $('#cartTableBody, .cart-items').empty();
    }
    
    showEmptyCartState() {
        const cartContainer = $('.cart-container, .cart-items-container');
        if (cartContainer.length) {
            cartContainer.html(`
                <div class="empty-cart text-center py-5">
                    <i class="fas fa-shopping-bag empty-cart-icon" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h3>Your cart is empty</h3>
                    <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
                    <a href="../index.php" class="btn btn-primary mt-3">Continue Shopping</a>
                </div>
            `);
        }
    }
    
    // ============================================================================
    // CART DRAWER/MODAL
    // ============================================================================
    
    toggleCartDrawer() {
        const drawer = $('#cartDrawer, .cart-drawer');
        if (drawer.hasClass('open')) {
            this.closeCartDrawer();
        } else {
            this.openCartDrawer();
        }
    }
    
    async openCartDrawer() {
        // Load latest cart items
        await this.loadCartItems();
        
        const drawer = $('#cartDrawer, .cart-drawer');
        const overlay = $('.cart-overlay');
        
        drawer.addClass('open');
        overlay.fadeIn(300);
        $('body').addClass('cart-drawer-open');
        
        // Trigger custom event
        this.triggerCartEvent('drawer_opened', {});
    }
    
    closeCartDrawer() {
        const drawer = $('#cartDrawer, .cart-drawer');
        const overlay = $('.cart-overlay');
        
        drawer.removeClass('open');
        overlay.fadeOut(300);
        $('body').removeClass('cart-drawer-open');
        
        // Trigger custom event
        this.triggerCartEvent('drawer_closed', {});
    }
    
    async loadCartItems() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=get_cart_items`);
            const data = await response.json();
            
            if (data.success) {
                this.renderCartItems(data.cart_items);
            }
            
        } catch (error) {
            console.error('Load cart items error:', error);
        }
    }
    
    renderCartItems(cartItems) {
        const container = $('.cart-drawer-items, .cart-items');
        
        if (!cartItems || cartItems.length === 0) {
            container.html('<div class="empty-cart-message">Your cart is empty</div>');
            return;
        }
        
        let html = '';
        cartItems.forEach(item => {
            html += `
                <div class="cart-item" data-cart-id="${item.id || item.cart_key}">
                    <div class="item-image">
                        <img src="${item.product_image || '../assets/images/default-product.jpg'}" 
                             alt="${item.product_name}" />
                    </div>
                    <div class="item-details">
                        <h6 class="item-name">${item.product_name}</h6>
                        ${item.size ? `<small class="item-size">Size: ${item.size}</small>` : ''}
                        <div class="item-price">₹${parseFloat(item.product_price).toFixed(2)}</div>
                    </div>
                    <div class="item-quantity">
                        <button class="quantity-btn decrease">-</button>
                        <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${item.stock_quantity}" readonly>
                        <button class="quantity-btn increase">+</button>
                    </div>
                    <div class="item-total">₹${parseFloat(item.total_price).toFixed(2)}</div>
                    <button class="remove-btn" title="Remove item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    // ============================================================================
    // UTILITY METHODS
    // ============================================================================
    
    setupPeriodicSync() {
        if (this.isLoggedIn) {
            // Sync cart every 2 minutes for logged-in users
            setInterval(() => {
                this.loadCartSummary();
            }, 120000);
        }
    }
    
    initializeQuantityControls() {
        // Set up quantity input validation
        $(document).on('input', '.quantity-input', function() {
            const input = $(this);
            const min = parseInt(input.attr('min')) || 1;
            const max = parseInt(input.attr('max')) || 999;
            let value = parseInt(input.val()) || min;
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            input.val(value);
        });
    }
    
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            // Ctrl/Cmd + Shift + C to open cart
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                this.toggleCartDrawer();
            }
            
            // Escape to close cart drawer
            if (e.key === 'Escape') {
                this.closeCartDrawer();
            }
        });
    }
    
    handleCartValidationIssues(issues) {
        let message = 'Some items in your cart need attention:\n\n';
        
        issues.forEach(issue => {
            switch (issue.type) {
                case 'insufficient_stock':
                    message += `• ${issue.item.product_name}: Only ${issue.available_quantity} available\n`;
                    break;
                case 'product_inactive':
                    message += `• ${issue.item.product_name}: No longer available\n`;
                    break;
                case 'price_changed':
                    message += `• ${issue.item.product_name}: Price changed to ₹${issue.new_price}\n`;
                    break;
            }
        });
        
        message += '\nWould you like to update your cart?';
        
        if (confirm(message)) {
            // Refresh the page to show updated cart
            window.location.reload();
        }
    }
    
    promptLogin(message = 'Please log in to add items to your cart.') {
        if (confirm(message + ' Would you like to log in now?')) {
            // Try to use One-Tap login first
            if (typeof triggerOneTapLogin === 'function') {
                triggerOneTapLogin();
            } else if (typeof initGoogleSignIn === 'function') {
                initGoogleSignIn();
            } else {
                // Fallback - refresh page and try again
                alert('Please refresh the page and try logging in again.');
                window.location.reload();
            }
        }
    }
    
    showNotification(message, type = 'info') {
        // Remove existing notifications
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
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.fadeOut(() => notification.remove());
        }, 5000);
    }
    
    triggerCartEvent(eventName, data) {
        $(document).trigger(`velona:cart:${eventName}`, data);
    }
    
    // ============================================================================
    // PUBLIC API METHODS
    // ============================================================================
    
    // Public method to add items to cart programmatically
    add(productId, quantity = 1, size = null, options = {}) {
        return this.addToCart(productId, quantity, size, options);
    }
    
    // Public method to update quantity programmatically
    update(cartId, quantity) {
        return this.updateQuantity(cartId, quantity);
    }
    
    // Public method to remove items programmatically
    remove(cartId) {
        return this.removeFromCart(cartId);
    }
    
    // Public method to clear cart programmatically
    clear() {
        return this.clearCart();
    }
    
    // Public method to get cart summary
    async getSummary() {
        await this.loadCartSummary();
        return this.cartCache;
    }
    
    // Public method to refresh cart
    refresh() {
        return this.loadCartSummary();
    }
    
    // Public method to open cart drawer
    open() {
        return this.openCartDrawer();
    }
    
    // Public method to close cart drawer
    close() {
        return this.closeCartDrawer();
    }
}

// ============================================================================
// INITIALIZATION AND GLOBAL SETUP
// ============================================================================

// Initialize cart when DOM is ready
$(document).ready(function() {
    // Initialize cart system
    window.VelonaCart = new VelonaCart();
    
    // Make cart available globally
    window.cart = window.VelonaCart;
    
    // Setup cart event listeners for custom functionality
    $(document).on('velona:cart:item_added', function(event, data) {
        console.log('Item added to cart:', data);
        
        // Update any custom UI elements
        updateCartRelatedUI(data);
    });
    
    $(document).on('velona:cart:quantity_updated', function(event, data) {
        console.log('Cart quantity updated:', data);
        
        // Update related UI
        updateCartRelatedUI(data);
    });
    
    $(document).on('velona:cart:item_removed', function(event, data) {
        console.log('Item removed from cart:', data);
        
        // Update related UI
        updateCartRelatedUI(data);
    });
    
    $(document).on('velona:cart:cart_cleared', function(event, data) {
        console.log('Cart cleared:', data);
        
        // Reset any custom UI
        resetCartUI();
    });
    
    // Handle login state changes
    $(document).on('user:logged_in', function(event, userData) {
        // Reinitialize cart for logged-in user
        window.VelonaCart.isLoggedIn = true;
        
        // Merge guest cart if exists
        mergeGuestCartOnLogin();
    });
    
    // Handle logout
    $(document).on('user:logged_out', function() {
        // Reset cart for guest user
        window.VelonaCart.isLoggedIn = false;
        window.VelonaCart.cartCache = null;
        window.VelonaCart.loadCartSummary();
    });
});

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function updateCartRelatedUI(data) {
    // Update free shipping progress bars
    if (data.cart_summary) {
        const threshold = data.cart_summary.free_shipping_threshold || 1000;
        const current = data.cart_summary.total_amount || 0;
        const progress = Math.min(100, (current / threshold) * 100);
        
        $('.free-shipping-progress .progress-bar').css('width', progress + '%');
    }
    
    // Update checkout button state
    const checkoutBtn = $('.checkout-btn');
    if (data.cart_summary && data.cart_summary.item_count > 0) {
        checkoutBtn.prop('disabled', false).removeClass('disabled');
    } else {
        checkoutBtn.prop('disabled', true).addClass('disabled');
    }
    
    // Update cart-dependent elements
    $('.cart-dependent').toggle((data.cart_summary?.item_count || 0) > 0);
}

function resetCartUI() {
    // Reset checkout button
    $('.checkout-btn').prop('disabled', true).addClass('disabled');
    
    // Hide cart-dependent elements
    $('.cart-dependent').hide();
    
    // Reset progress bars
    $('.free-shipping-progress .progress-bar').css('width', '0%');
}

async function mergeGuestCartOnLogin() {
    try {
        const response = await fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'merge_guest_cart'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Refresh cart after merge
            window.VelonaCart.loadCartSummary();
            window.VelonaCart.showNotification('Cart updated after login', 'success');
        }
    } catch (error) {
        console.error('Error merging guest cart:', error);
    }
}

// ============================================================================
// LEGACY SUPPORT FUNCTIONS (for backward compatibility)
// ============================================================================

// Legacy function names for backward compatibility
window.addToCart = function(productId, quantity, size) {
    return window.VelonaCart.add(productId, quantity, size);
};

window.updateCartQuantity = function(cartId, quantity) {
    return window.VelonaCart.update(cartId, quantity);
};

window.removeFromCart = function(cartId) {
    return window.VelonaCart.remove(cartId);
};

window.clearCart = function() {
    return window.VelonaCart.clear();
};

window.openCartDrawer = function() {
    return window.VelonaCart.open();
};

window.closeCartDrawer = function() {
    return window.VelonaCart.close();
};

// ============================================================================
// CART ANALYTICS (Optional)
// ============================================================================

function trackCartEvent(action, data = {}) {
    // Google Analytics 4
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            event_category: 'cart',
            ...data
        });
    }
    
    // Facebook Pixel
    if (typeof fbq !== 'undefined') {
        switch (action) {
            case 'add_to_cart':
                fbq('track', 'AddToCart', {
                    content_ids: [data.product_id],
                    content_type: 'product',
                    value: data.price,
                    currency: 'INR'
                });
                break;
            case 'remove_from_cart':
                fbq('track', 'RemoveFromCart', {
                    content_ids: [data.product_id],
                    content_type: 'product'
                });
                break;
        }
    }
    
    // Custom analytics
    if (window.velonaAnalytics) {
        window.velonaAnalytics.track(action, data);
    }
}

// ============================================================================
// CART PERSISTENCE (for guests)
// ============================================================================

function saveGuestCartToStorage() {
    if (!window.VelonaCart.isLoggedIn && window.VelonaCart.cartCache) {
        try {
            const cartData = {
                cart: window.VelonaCart.cartCache,
                timestamp: Date.now(),
                version: '1.0'
            };
            
            localStorage.setItem('velona_guest_cart', JSON.stringify(cartData));
        } catch (e) {
            console.warn('Could not save guest cart to storage:', e);
        }
    }
}

function loadGuestCartFromStorage() {
    if (!window.VelonaCart.isLoggedIn) {
        try {
            const saved = localStorage.getItem('velona_guest_cart');
            if (saved) {
                const cartData = JSON.parse(saved);
                
                // Check if data is recent (within 7 days)
                const daysSaved = (Date.now() - cartData.timestamp) / (1000 * 60 * 60 * 24);
                
                if (daysSaved <= 7) {
                    // Restore cart data
                    window.VelonaCart.cartCache = cartData.cart;
                    window.VelonaCart.updateCartBadge(cartData.cart.item_count || 0);
                } else {
                    // Remove old data
                    localStorage.removeItem('velona_guest_cart');
                }
            }
        } catch (e) {
            console.warn('Could not load guest cart from storage:', e);
        }
    }
}

// Auto-save guest cart periodically
setInterval(saveGuestCartToStorage, 30000); // Every 30 seconds

// Save on page unload
window.addEventListener('beforeunload', saveGuestCartToStorage);

// Load on page load
window.addEventListener('load', loadGuestCartFromStorage);