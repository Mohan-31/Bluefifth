document.addEventListener('DOMContentLoaded', function() {
    // Initialize referral UI elements
    initReferralUI();
});

function initReferralUI() {
    // Load referral data
    loadReferralData();
    
    // Set up copy buttons
    setupCopyButtons();
    
    // Set up claim button
    setupClaimButton();
}

function loadReferralData() {
    // Fetch referral data from the server
    fetch('/wallet/get-balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI with referral information
                updateReferralUI(data);
            } else {
                console.error('Failed to load referral data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading referral data:', error);
        });
}

function updateReferralUI(data) {
    // Update referral code and link
    const codeElement = document.getElementById('referral-code');
    const linkElement = document.getElementById('referral-link');
    
    if (codeElement) {
        codeElement.textContent = data.referral.code;
    }
    
    if (linkElement) {
        linkElement.textContent = data.referral.link;
        linkElement.href = data.referral.link;
    }
    
    // Update statistics
    const visitCountElement = document.getElementById('visit-count');
    const purchaseCountElement = document.getElementById('purchase-count');
    const pointsEarnedElement = document.getElementById('points-earned');
    
    if (visitCountElement) {
        visitCountElement.textContent = data.referral.visit_count;
    }
    
    if (purchaseCountElement) {
        purchaseCountElement.textContent = data.referral.purchase_count;
    }
    
    // Update wallet balance
    const availablePointsElement = document.getElementById('available-points');
    const pendingPointsElement = document.getElementById('pending-points');
    
    if (availablePointsElement) {
        availablePointsElement.textContent = data.balance.available;
    }
    
    if (pendingPointsElement) {
        pendingPointsElement.textContent = data.balance.pending;
    }
    
    // Update claim button status
    const claimButton = document.getElementById('claim-button');
    if (claimButton) {
        if (data.can_claim && data.balance.pending > 0) {
            claimButton.disabled = false;
            claimButton.classList.remove('disabled');
        } else {
            claimButton.disabled = true;
            claimButton.classList.add('disabled');
            
            // Add tooltip explaining why claiming is disabled
            let tooltipText = 'Claims are only available at the end of the month';
            if (data.balance.pending <= 0) {
                tooltipText = 'No pending points to claim';
            }
            claimButton.setAttribute('title', tooltipText);
        }
    }
}

function setupCopyButtons() {
    // Set up code copy button
    const copyCodeButton = document.getElementById('copy-code-button');
    if (copyCodeButton) {
        copyCodeButton.addEventListener('click', function() {
            const codeElement = document.getElementById('referral-code');
            copyToClipboard(codeElement.textContent);
            showCopyFeedback(copyCodeButton, 'Copied!');
        });
    }
    
    // Set up link copy button
    const copyLinkButton = document.getElementById('copy-link-button');
    if (copyLinkButton) {
        copyLinkButton.addEventListener('click', function() {
            const linkElement = document.getElementById('referral-link');
            copyToClipboard(linkElement.textContent);
            showCopyFeedback(copyLinkButton, 'Copied!');
        });
    }
}

function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
}

function showCopyFeedback(element, message) {
    const originalText = element.textContent;
    element.textContent = message;
    
    setTimeout(() => {
        element.textContent = originalText;
    }, 2000);
}

function setupClaimButton() {
    const claimButton = document.getElementById('claim-button');
    if (claimButton) {
        claimButton.addEventListener('click', function() {
            if (claimButton.disabled) return;
            
            if (confirm('Are you sure you want to claim your points? This will notify the admin to process your payment.')) {
                // Send claim request to server
                fetch('/referral/claim-points.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Claim successful! Admin has been notified and will process your payment.');
                            
                            // Reload referral data to update UI
                            loadReferralData();
                        } else {
                            alert('Failed to claim points: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error claiming points:', error);
                        alert('An error occurred while claiming points. Please try again.');
                    });
            }
        });
    }
}