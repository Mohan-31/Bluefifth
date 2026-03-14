<?php
/*
 * VELONA ADMIN PANEL - STANDARDIZED NAVIGATION COMPONENT
 * This file provides consistent sidebar navigation across all admin pages
 * with proper active page detection and responsive design
 */

// Get current page filename for active state detection
$current_page = basename($_SERVER['PHP_SELF']);

// Define navigation items in the exact order specified
$nav_items = [
    [
        'file' => 'admin.php',
        'icon' => 'fas fa-dashboard',
        'label' => 'Dashboard'
    ],
    [
        'file' => 'orders.php', 
        'icon' => 'fas fa-shopping-cart',
        'label' => 'Orders'
    ],
    [
        'file' => 'returns.php', 
        'icon' => 'fas fa-undo-alt',
        'label' => 'Returns'
    ],
    [
        'file' => 'customers.php',
        'icon' => 'fas fa-user-friends', 
        'label' => 'Customers'
    ],
    [
        'file' => 'categories.php',
        'icon' => 'fas fa-tags',
        'label' => 'Categories'
    ],
    [
        'file' => 'products.php',
        'icon' => 'fas fa-box',
        'label' => 'Products'
    ],
    [
        'file' => 'coupons.php',
        'icon' => 'fas fa-ticket-alt',
        'label' => 'Coupon Codes'
    ],
    [
        'file' => 'referral-dashboard.php',
        'icon' => 'fas fa-share-alt',
        'label' => 'Referral Dashboard'
    ],
    [
        'file' => 'settings.php',
        'icon' => 'fas fa-cog',
        'label' => 'Settings'
    ],
    [
        'file' => 'admin-logout.php',
        'icon' => 'fas fa-sign-out-alt',
        'label' => 'Logout'
    ]
];
?>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle-btn" onclick="toggleMobileSidebar()" id="mobileToggleBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <!-- Sidebar Brand -->
    <div class="sidebar-brand">
        <h4>
            <i class="fas fa-store me-2"></i>
            <span class="sidebar-text">Velona Admin</span>
        </h4>
    </div>
    
    <!-- Navigation Links -->
    <ul class="nav flex-column">
        <?php foreach ($nav_items as $item): ?>
            <li class="nav-item">
                <a href="<?php echo $item['file']; ?>" 
                   class="nav-link <?php echo ($current_page === $item['file']) ? 'active' : ''; ?>"
                   onclick="handleNavClick()">
                    <i class="<?php echo $item['icon']; ?> me-2"></i>
                    <span class="sidebar-text"><?php echo $item['label']; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Toggle Sidebar Button (Desktop Only) -->
    <button class="toggle-sidebar-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
</nav>

<!-- Sidebar Toggle Script -->
<script>
// Toggle sidebar functionality (Desktop)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const adminContainer = document.querySelector('.admin-container');
    
    if (window.innerWidth > 992) {
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            
            // Handle both main-content and admin-container
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
            if (adminContainer) {
                adminContainer.classList.toggle('expanded');
            }
            
            // Store sidebar state in localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('admin_sidebar_collapsed', isCollapsed);
        }
    }
}

// Mobile sidebar functions
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const toggleBtn = document.getElementById('mobileToggleBtn');
    
    if (sidebar && overlay && toggleBtn) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        
        // Change icon
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('mobile-open')) {
            icon.className = 'fas fa-times';
        } else {
            icon.className = 'fas fa-bars';
        }
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    const toggleBtn = document.getElementById('mobileToggleBtn');
    
    if (sidebar && overlay && toggleBtn) {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        
        // Reset icon
        const icon = toggleBtn.querySelector('i');
        icon.className = 'fas fa-bars';
    }
}

// Handle navigation clicks on mobile
function handleNavClick() {
    if (window.innerWidth <= 992) {
        closeMobileSidebar();
    }
}

// Restore sidebar state on page load
document.addEventListener('DOMContentLoaded', function() {
    // Desktop sidebar state
    const isCollapsed = localStorage.getItem('admin_sidebar_collapsed') === 'true';
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const adminContainer = document.querySelector('.admin-container');
    
    if (isCollapsed && sidebar && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        if (mainContent) {
            mainContent.classList.add('expanded');
        }
        if (adminContainer) {
            adminContainer.classList.add('expanded');
        }
    }
    
    // Initialize responsive behavior
    handleResponsiveSidebar();
    window.addEventListener('resize', handleResponsiveSidebar);
});

// Handle responsive sidebar behavior
function handleResponsiveSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const adminContainer = document.querySelector('.admin-container');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth <= 992) {
        // Mobile: Reset sidebar state
        if (sidebar) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('mobile-open');
        }
        if (mainContent) {
            mainContent.classList.remove('expanded');
        }
        if (adminContainer) {
            adminContainer.classList.remove('expanded');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Reset mobile toggle button icon
        const toggleBtn = document.getElementById('mobileToggleBtn');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            icon.className = 'fas fa-bars';
        }
    } else {
        // Desktop: Restore collapsed state if previously set
        const isCollapsed = localStorage.getItem('admin_sidebar_collapsed') === 'true';
        if (isCollapsed && sidebar) {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('expanded');
            }
            if (adminContainer) {
                adminContainer.classList.add('expanded');
            }
        }
        
        // Close mobile overlay if open
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
}

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('mobileToggleBtn');
    
    if (window.innerWidth <= 992 && 
        sidebar && sidebar.classList.contains('mobile-open') &&
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target)) {
        closeMobileSidebar();
    }
});

// Add smooth transitions for better UX
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebar) {
        sidebar.style.transition = 'all 0.3s ease';
    }
    if (mainContent) {
        mainContent.style.transition = 'all 0.3s ease';
    }
});

// Handle active link highlighting for dynamic pages
function setActiveNavLink(pageName) {
    // Remove active class from all links
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => link.classList.remove('active'));
    
    // Add active class to current page link
    const activeLink = document.querySelector(`.sidebar .nav-link[href="${pageName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
}
</script>