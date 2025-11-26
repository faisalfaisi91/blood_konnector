/**
 * Show the selected tab and hide others
 * @param {string} tabId - The ID of the tab to show
 */
function showTab(tabId) {
    console.log('showTab called with:', tabId); // Debug log
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
        tab.style.display = 'none';
    });
    
    // Remove active state from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
        button.setAttribute('aria-selected', 'false');
    });
    
    // Show selected tab content
    const activeTab = document.getElementById(tabId);
    if (activeTab) {
        activeTab.classList.remove('hidden');
        activeTab.style.display = 'block';
        activeTab.setAttribute('aria-hidden', 'false');
        
        // Simulate loading data
        simulateTableLoading(activeTab);
        
        // Initialize settings form if settings tab is shown
        if (tabId === 'settings') {
            setTimeout(() => {
                const settingsForm = document.getElementById('settingsForm');
                if (settingsForm && !settingsForm.hasAttribute('data-initialized')) {
                    handleSettingsForm();
                    settingsForm.setAttribute('data-initialized', 'true');
                }
            }, 100);
        }
    } else {
        console.error('Tab not found:', tabId); // Debug log
    }
    
    // Set active state on clicked button
    const activeButton = document.querySelector(`.tab-button[data-tab="${tabId}"]`);
    if (activeButton) {
        activeButton.classList.add('active');
        activeButton.setAttribute('aria-selected', 'true');
    } else {
        console.error('Tab button not found for:', tabId); // Debug log
    }
    
    // Update URL hash for deep linking
    updateUrlHash(tabId);
}

/**
 * Filter table rows based on search input
 * @param {string} tableId - The ID of the table to filter
 * @param {string} query - The search query
 */
function filterTable(tableId, query) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr:not(.no-results)');
    const noResultsRow = table.querySelector('tr.no-results');
    query = query.toLowerCase().trim();
    
    let hasVisibleRows = false;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const isVisible = text.includes(query);
        row.style.display = isVisible ? '' : 'none';
        
        if (isVisible) hasVisibleRows = true;
    });
    
    // Show/hide no results message
    if (noResultsRow) {
        noResultsRow.style.display = hasVisibleRows ? 'none' : '';
    }
}

/**
 * Update URL hash for deep linking
 * @param {string} tabId - The ID of the active tab
 */
function updateUrlHash(tabId) {
    if (history.pushState) {
        const newUrl = window.location.pathname + '#' + tabId;
        window.history.pushState({ path: newUrl }, '', newUrl);
    }
}

/**
 * Simulate loading state for tables
 * @param {HTMLElement} tabElement - The tab element containing tables
 */
function simulateTableLoading(tabElement) {
    const tables = tabElement.querySelectorAll('table tbody');
    
    tables.forEach(tbody => {
        tbody.classList.add('loading');
        
        // Remove loading state after delay (simulating data fetch)
        setTimeout(() => {
            tbody.classList.remove('loading');
        }, 600);
    });
}

/**
 * Handle settings form submission
 */
function handleSettingsForm() {
    const settingsForm = document.getElementById('settingsForm');
    if (!settingsForm) return;
    
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(settingsForm);
        const submitButton = settingsForm.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        
        // Disable button and show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        
        try {
            const response = await fetch('save-settings.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            // Show success/error message
            showNotification(result.message, result.success ? 'success' : 'error');
            
            if (result.success) {
                // Reset button after short delay
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }, 1500);
            } else {
                // Re-enable button on error
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        } catch (error) {
            showNotification('An error occurred while saving settings. Please try again.', 'error');
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    });
}

/**
 * Show notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, info)
 */
function showNotification(message, type = 'info') {
    // Remove existing notification if any
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-circle' : 
                 'fa-info-circle';
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

/**
 * Debounce function to limit how often a function is called
 * @param {Function} func - The function to debounce
 * @param {number} wait - The delay in milliseconds
 * @returns {Function} - The debounced function
 */
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Set up tab switching functionality
 */
function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    console.log('Found tab buttons:', tabButtons.length); // Debug log
    
    tabButtons.forEach((button, index) => {
        console.log(`Setting up button ${index}:`, button.getAttribute('data-tab')); // Debug log
        
        // Remove any existing listeners
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const tabId = this.getAttribute('data-tab');
            console.log('Button clicked, tabId:', tabId); // Debug log
            if (tabId) {
                showTab(tabId);
            }
        });
        
        // Ensure button is clickable
        newButton.style.pointerEvents = 'auto';
        newButton.style.cursor = 'pointer';
        newButton.style.position = 'relative';
        newButton.style.zIndex = '10';
    });
}

/**
 * Set up event listeners for table filters
 */
function setupTableFilters() {
    document.querySelectorAll('[id$="Search"]').forEach(searchInput => {
        searchInput.addEventListener('input', (e) => {
            const tableId = e.target.id.replace('Search', 'Table');
            filterTable(tableId, e.target.value);
        });
    });
}

/**
 * Set up animations and transitions
 */
function setupAnimations() {
    // Add hover effects to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-4px)';
            card.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
            card.style.boxShadow = '';
        });
    });
    
    // Check for hash on load and switch to that tab
    if (window.location.hash) {
        const tabId = window.location.hash.substring(1);
        if (document.getElementById(tabId)) {
            setTimeout(() => showTab(tabId), 100);
        }
    }
}

/**
 * Initialize dashboard components
 */
function initDashboard() {
    // Set up tab switching
    setupTabs();
    
    // Set up table filtering
    setupTableFilters();
    
    // Show initial tab (users by default)
    const initialTab = document.getElementById('users');
    if (initialTab) {
        initialTab.classList.remove('hidden');
        initialTab.style.display = 'block';
    }
    showTab('users');
    
    // Add any animations or transitions
    setupAnimations();
}

// Make functions available globally
window.showTab = showTab;
window.filterTable = debounce(filterTable, 300);

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('Dashboard initializing...'); // Debug log
    initDashboard();
    console.log('Dashboard initialized'); // Debug log
});
