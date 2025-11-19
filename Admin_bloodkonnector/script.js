document.addEventListener('DOMContentLoaded', () => {
    // Initialize the dashboard
    initDashboard();
});

/**
 * Initialize dashboard components
 */
function initDashboard() {
    // Set up tab switching
    setupTabs();
    
    // Set up table filtering
    setupTableFilters();
    
    // Show initial tab (users by default)
    showTab('users');
    
    // Add any animations or transitions
    setupAnimations();
}

/**
 * Set up tab switching functionality
 */
function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const tabId = e.currentTarget.getAttribute('data-tab');
            showTab(tabId);
        });
    });
}

/**
 * Show the selected tab and hide others
 * @param {string} tabId - The ID of the tab to show
 */
function showTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
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
        activeTab.setAttribute('aria-hidden', 'false');
        
        // Simulate loading data
        simulateTableLoading(activeTab);
    }
    
    // Set active state on clicked button
    const activeButton = document.querySelector(`.tab-button[data-tab="${tabId}"]`);
    if (activeButton) {
        activeButton.classList.add('active');
        activeButton.setAttribute('aria-selected', 'true');
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

// Make functions available globally (only what's needed)
window.showTab = showTab;
window.filterTable = debounce(filterTable, 300);