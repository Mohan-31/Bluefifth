document.addEventListener('DOMContentLoaded', function() {
    // Initialize wallet UI on checkout page
    initWalletCheckout();
});

function initWalletCheckout() {
    // Load wallet balance
    loadWalletBalance();
    
    // Set up points slider
    setupPointsSlider();
    
    // Set up apply points button
    setupApplyPointsButton();
}

function loadWalletBalance() {
    // Fetch wallet balance from server
    fetch('/wallet/get-balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI with wallet information
                updateWalletUI(data);
            } else {
                console.error('Failed to load wallet data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading wallet data:', error);
        });
}

function updateWalletUI(data) {
    // Update available points display
    const availablePointsElement = document.getElementById('available-points');
    if (availablePointsElement) {
        availablePointsElement.textContent = data.balance.available;
    }
    
    // Set maximum value for points slider
    const pointsSlider = document.getElementById('points-slider');
    if (pointsSlider) {
        pointsSlider.max = data.balance.available;
        
        // If user has no points, disable slider and apply button
        if (data.balance.available <= 0) {
            pointsSlider.disabled = true;
            
            const applyButton = document.getElementById('apply-points-button');
            if (applyButton) {
                applyButton.disabled = true;
                applyButton.classList.add('disabled');
            }
            
            const noPointsMessage = document.getElementById('no-points-message');
            if (noPointsMessage) {
                noPointsMessage.style.display = 'block';
            }
        }
    }
}

function setupPointsSlider() {
    const pointsSlider = document.getElementById('points-slider');
    const pointsValue = document.getElementById('points-value');
    const discountPreview = document.getElementById('discount-preview');
    
    if (pointsSlider && pointsValue) {
        pointsSlider.addEventListener('input', function() {
            // Update points value display
            pointsValue.textContent = pointsSlider.value;
            
            // Calculate discount preview
            if (discountPreview) {
                const orderTotal = parseFloat(document.getElementById('order-total').dataset.value || 0);
                const discountPercent = Math.min(100, parseInt(pointsSlider.value));
                const discountAmount = (orderTotal * discountPercent) / 100;
                
                discountPreview.textContent = '₹' + discountAmount.toFixed(2);
            }
        });
        
        // Initialize with default value
        pointsValue.textContent = pointsSlider.value;
    }
}

function setupApplyPointsButton() {
    const applyButton = document.getElementById('apply-points-button');
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            const pointsSlider = document.getElementById('points-slider');
            const orderTotal = parseFloat(document.getElementById('order-total').dataset.value || 0);
            
            if (!pointsSlider || pointsSlider.disabled) return;
            
            const pointsToUse = parseInt(pointsSlider.value);
            
            // Send apply points request to server
            fetch('/wallet/use-points.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'points_to_use=' + pointsToUse + '&order_total=' + orderTotal
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update order total display
                    const orderTotalElement = document.getElementById('order-total');
                    const discountElement = document.getElementById('discount-amount');
                    const newTotalElement = document.getElementById('new-total');
                    
                    if (orderTotalElement) {
                        orderTotalElement.textContent = '₹' + orderTotal.toFixed(2);
                    }
                    
                    if (discountElement) {
                        discountElement.textContent = '₹' + data.discount_amount.toFixed(2);
                        discountElement.parentElement.style.display = 'block';
                    }
                    
                    if (newTotalElement) {
                        newTotalElement.textContent = '₹' + data.new_total.toFixed(2);
                        
                        // Update Razorpay amount if available
                        const razorpayButton = document.querySelector('[data-razorpay-amount]');
                        if (razorpayButton) {
                            razorpayButton.setAttribute('data-razorpay-amount', data.new_total * 100); // Razorpay uses paise
                        }
                    }
                    
                    // Disable slider and button after applying
                    pointsSlider.disabled = true;
                    applyButton.disabled = true;
                    applyButton.classList.add('disabled');
                    applyButton.textContent = 'Points Applied';
                    
                    // Add reset button
                    const resetButton = document.createElement('button');
                    resetButton.textContent = 'Reset';
                    resetButton.classList.add('btn', 'btn-sm', 'btn-secondary', 'ml-2');
                    resetButton.addEventListener('click', function() {
                        window.location.reload();
                    });
                    
                    applyButton.parentNode.appendChild(resetButton);
                } else {
                    alert('Failed to apply points: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error applying points:', error);
                alert('An error occurred while applying points. Please try again.');
            });
        });
    }
}