/**
 * Profile Switcher Component JavaScript
 * Handles dropdown toggling and user interactions
 */

// Track if already initialized to prevent duplicate listeners
let profileSwitcherInitialized = false;

// Initialize profile switcher - use both DOMContentLoaded and immediate execution
function initProfileSwitcher() {
    // Prevent multiple initializations
    if (profileSwitcherInitialized) {
        return;
    }
    
    const switcherBtn = document.getElementById('profileSwitcherBtn');
    const switcherMenu = document.getElementById('profileSwitcherMenu');
    
    if (!switcherBtn || !switcherMenu) {
        return; // Elements don't exist on this page
    }
    
    // Mark as initialized
    profileSwitcherInitialized = true;
    
    // Ensure button is clickable with highest priority
    switcherBtn.style.pointerEvents = 'auto';
    switcherBtn.style.cursor = 'pointer';
    switcherBtn.style.position = 'relative';
    switcherBtn.style.zIndex = '10001';
    switcherBtn.setAttribute('tabindex', '0');
    
    // Ensure menu has proper z-index
    switcherMenu.style.zIndex = '10002';
    switcherMenu.style.pointerEvents = 'auto';
    
    // Use current references
    const currentSwitcherBtn = switcherBtn;
    const currentSwitcherMenu = switcherMenu;
    
    // Toggle dropdown on button click - use capture phase to ensure it fires first
    currentSwitcherBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        console.log('Profile switcher button clicked'); // Debug log
        toggleDropdown();
    }, true); // Use capture phase
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!currentSwitcherBtn.contains(e.target) && !currentSwitcherMenu.contains(e.target)) {
            closeDropdown();
        }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    currentSwitcherMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    /**
     * Toggle dropdown visibility
     */
    function toggleDropdown() {
        const isOpen = currentSwitcherMenu.classList.contains('show');
        
        if (isOpen) {
            closeDropdown();
        } else {
            openDropdown();
        }
    }
    
    /**
     * Open dropdown
     */
    function openDropdown() {
        currentSwitcherMenu.classList.add('show');
        currentSwitcherBtn.classList.add('active');
        currentSwitcherBtn.setAttribute('aria-expanded', 'true');
    }
    
    /**
     * Close dropdown
     */
    function closeDropdown() {
        currentSwitcherMenu.classList.remove('show');
        currentSwitcherBtn.classList.remove('active');
        currentSwitcherBtn.setAttribute('aria-expanded', 'false');
    }
    
    // Keyboard navigation for accessibility
    const profileOptions = currentSwitcherMenu.querySelectorAll('.profile-option');
    let currentFocusIndex = -1;
    
    currentSwitcherBtn.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openDropdown();
            
            if (profileOptions.length > 0) {
                currentFocusIndex = 0;
                profileOptions[0].focus();
            }
        }
    });
    
    profileOptions.forEach((option, index) => {
        option.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocusIndex = (index + 1) % profileOptions.length;
                profileOptions[currentFocusIndex].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocusIndex = (index - 1 + profileOptions.length) % profileOptions.length;
                profileOptions[currentFocusIndex].focus();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeDropdown();
                currentSwitcherBtn.focus();
            }
        });
    });
    
    // Add smooth transition effect
    currentSwitcherMenu.style.transition = 'all 0.3s ease';
    currentSwitcherMenu.style.zIndex = '10002';
    currentSwitcherMenu.style.pointerEvents = 'auto';
}

// Try to initialize immediately if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfileSwitcher);
} else {
    // DOM is already loaded
    initProfileSwitcher();
}

// Also try after a short delay to ensure all scripts are loaded
setTimeout(initProfileSwitcher, 100);

// Legacy DOMContentLoaded listener for backward compatibility
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure other scripts have initialized
    setTimeout(initProfileSwitcher, 50);
    
    // Show first-time user tooltip (optional)
    setTimeout(function() {
        const hasSeenTooltip = localStorage.getItem('profileSwitcherTooltipSeen');
        const profileOptions = document.querySelectorAll('.profile-option');
        
        if (!hasSeenTooltip && profileOptions.length > 1) {
            // User has multiple profiles and hasn't seen tooltip
            setTimeout(function() {
                showTooltip();
                
                // Mark as seen after 5 seconds
                setTimeout(function() {
                    hideTooltip();
                    localStorage.setItem('profileSwitcherTooltipSeen', 'true');
                }, 5000);
            }, 1000);
        }
    }, 200);
    
    /**
     * Show tooltip for first-time users
     */
    function showTooltip() {
        // Only show if element doesn't already have a tooltip
        if (document.querySelector('.profile-switcher-tooltip')) {
            return;
        }
        
        const tooltip = document.createElement('div');
        tooltip.className = 'profile-switcher-tooltip';
        tooltip.textContent = 'Switch between your profiles here';
        
        const dropdown = document.querySelector('.profile-switcher-dropdown');
        if (dropdown) {
            dropdown.appendChild(tooltip);
            
            // Trigger reflow for animation
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }, 10);
        }
    }
    
    /**
     * Hide tooltip
     */
    function hideTooltip() {
        const tooltip = document.querySelector('.profile-switcher-tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
            
            setTimeout(() => {
                tooltip.remove();
            }, 300);
        }
    }
});

/**
 * Mobile overlay for better UX on small screens
 */
if (window.innerWidth <= 768) {
    document.addEventListener('DOMContentLoaded', function() {
        const switcherBtn = document.getElementById('profileSwitcherBtn');
        const switcherMenu = document.getElementById('profileSwitcherMenu');
        
        if (!switcherBtn || !switcherMenu) {
            return;
        }
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'profile-switcher-overlay';
        document.body.appendChild(overlay);
        
        // Show/hide overlay with menu
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (switcherMenu.classList.contains('show')) {
                        overlay.classList.add('show');
                    } else {
                        overlay.classList.remove('show');
                    }
                }
            });
        });
        
        observer.observe(switcherMenu, { attributes: true });
        
        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            switcherMenu.classList.remove('show');
            switcherBtn.classList.remove('active');
        });
    });
}

/**
 * Switch profile for mobile menu
 * @param {string} profile - 'donor' or 'recipient'
 */
function switchProfileMobile(profile) {
    
    // Show loading state
    const buttons = document.querySelectorAll('.mobile-profile-switcher button');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.6';
    });
    
    // Get the base URL from current location
    const currentPath = window.location.pathname;
    const pathSegments = currentPath.split('/').filter(part => part);
    
    // Find the project root (blood_konnector)
    let baseUrl = window.location.origin;
    if (pathSegments.length > 0 && pathSegments[0] === 'blood_konnector') {
        baseUrl += '/blood_konnector';
    }
    
    const ajaxUrl = baseUrl + '/assets/lib/switch-profile.php';
    
    // Make AJAX request
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'profile=' + encodeURIComponent(profile)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const profilePage = profile === 'donor' ? 'donor-profile' : 'recipient-profile';
            window.location.href = baseUrl + '/' + profilePage;
        } else {
            // Show error message
            console.error('Profile switch failed:', data.message);
            alert(data.message || 'Failed to switch profile. Please try again.');
            
            // Re-enable buttons
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    })
    .catch(error => {
        alert('An error occurred: ' + error.message + '. Please try again.');
        
        // Re-enable buttons
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '1';
        });
    });
}
