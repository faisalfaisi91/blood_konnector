/**
 * Profile Switcher Component JavaScript
 * Handles dropdown toggling and user interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    const switcherBtn = document.getElementById('profileSwitcherBtn');
    const switcherMenu = document.getElementById('profileSwitcherMenu');
    
    if (!switcherBtn || !switcherMenu) {
        return; // Elements don't exist on this page
    }
    
    // Toggle dropdown on button click
    switcherBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!switcherBtn.contains(e.target) && !switcherMenu.contains(e.target)) {
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
    switcherMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    /**
     * Toggle dropdown visibility
     */
    function toggleDropdown() {
        const isOpen = switcherMenu.classList.contains('show');
        
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
        switcherMenu.classList.add('show');
        switcherBtn.classList.add('active');
        switcherBtn.setAttribute('aria-expanded', 'true');
    }
    
    /**
     * Close dropdown
     */
    function closeDropdown() {
        switcherMenu.classList.remove('show');
        switcherBtn.classList.remove('active');
        switcherBtn.setAttribute('aria-expanded', 'false');
    }
    
    // Keyboard navigation for accessibility
    const profileOptions = switcherMenu.querySelectorAll('.profile-option');
    let currentFocusIndex = -1;
    
    switcherBtn.addEventListener('keydown', function(e) {
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
                switcherBtn.focus();
            }
        });
    });
    
    // Add smooth transition effect
    switcherMenu.style.transition = 'all 0.3s ease';
    
    // Show first-time user tooltip (optional)
    const hasSeenTooltip = localStorage.getItem('profileSwitcherTooltipSeen');
    
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

