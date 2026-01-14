/**
 * Notification Bell Component
 * Handles fetching, displaying, and auto-refreshing notifications
 */

let notificationRefreshInterval = null;
const NOTIFICATION_REFRESH_INTERVAL = 30000; // 30 seconds

// Initialize notification bell when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationBellMobile = document.getElementById('notificationBellMobile');
    if (notificationBell || notificationBellMobile) {
        initNotificationBell();
    }
});

function initNotificationBell() {
    // Load notifications on page load
    loadNotifications();
    
    // Set up auto-refresh
    startNotificationAutoRefresh();
    
    // Refresh when dropdown is opened (desktop)
    const notificationBell = document.getElementById('notificationBell');
    if (notificationBell) {
        notificationBell.addEventListener('shown.bs.dropdown', function() {
            loadNotifications();
        });
    }
    
    // Refresh when dropdown is opened (mobile)
    const notificationBellMobile = document.getElementById('notificationBellMobile');
    if (notificationBellMobile) {
        notificationBellMobile.addEventListener('shown.bs.dropdown', function() {
            loadNotifications();
        });
    }
}

function loadNotifications() {
    fetch('assets/lib/emergency-api.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count || 0);
                renderNotifications(data.notifications || []);
            } else {
                console.error('Failed to load notifications:', data.error);
                showNotificationError();
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            showNotificationError();
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    const badgeMobile = document.getElementById('notificationBadgeMobile');
    
    [badge, badgeMobile].forEach(b => {
        if (b) {
            if (count > 0) {
                b.textContent = count > 99 ? '99+' : count;
                b.style.display = 'inline-block';
            } else {
                b.style.display = 'none';
            }
        }
    });
}

function renderNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    const notificationListMobile = document.getElementById('notificationListMobile');
    
    if (!notificationList && !notificationListMobile) return;
    
    if (notifications.length === 0) {
        const emptyHtml = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p class="mb-0">No notifications yet</p>
            </div>
        `;
        if (notificationList) notificationList.innerHTML = emptyHtml;
        if (notificationListMobile) notificationListMobile.innerHTML = emptyHtml;
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        const isRead = notif.status === 'sent';
        const payload = notif.payload_obj || {};
        const requestId = payload.request_id || notif.request_id || null;
        
        let iconClass = 'fa-heartbeat text-danger';
        let title = 'New Emergency Request';
        let message = '';
        let actionUrl = '';
        let actionText = '';
        
        if (notif.template_key === 'emergency_new_request') {
            iconClass = 'fa-heartbeat text-danger';
            title = 'New Emergency Request';
            message = `A new emergency request matches your profile`;
            if (notif.blood_type) {
                message += ` (Blood Type: ${escapeHtml(notif.blood_type)}`;
                if (notif.city) {
                    message += `, City: ${escapeHtml(notif.city)}`;
                }
                message += ')';
            }
            if (requestId) {
                actionUrl = 'emergency-donor.php#request-' + requestId;
                actionText = 'View Request';
            }
        } else if (notif.template_key === 'emergency_donor_approved') {
            iconClass = 'fa-check-circle text-success';
            title = 'Donor Approved Your Request';
            if (notif.donor_first || notif.donor_last) {
                const donorName = escapeHtml((notif.donor_first || '') + ' ' + (notif.donor_last || '')).trim();
                message = `${donorName} has approved your emergency request.`;
            } else {
                message = 'A donor has approved your emergency request.';
            }
            if (requestId) {
                actionUrl = 'emergency-recipient.php#request-' + requestId;
                actionText = 'View Request';
            }
        } else if (notif.template_key === 'lifeline_new_request') {
            iconClass = 'fa-heartbeat text-danger';
            title = 'New LifeLine Request';
            message = `A new LifeLine request matches your profile`;
            if (notif.blood_type) {
                message += ` (Blood Type: ${escapeHtml(notif.blood_type)}`;
                if (notif.city) {
                    message += `, City: ${escapeHtml(notif.city)}`;
                }
                message += ')';
            }
            if (requestId) {
                actionUrl = 'lifeline-donor-requests#request-' + requestId;
                actionText = 'View Request';
            }
        } else if (notif.template_key === 'lifeline_donor_approved') {
            iconClass = 'fa-check-circle text-success';
            title = 'Donor Approved Your LifeLine Request';
            if (notif.donor_first || notif.donor_last) {
                const donorName = escapeHtml((notif.donor_first || '') + ' ' + (notif.donor_last || '')).trim();
                message = `${donorName} has approved your LifeLine request.`;
            } else {
                message = 'A donor has approved your LifeLine request.';
            }
            if (requestId) {
                actionUrl = 'lifeline-panel#request-' + requestId;
                actionText = 'View Request';
            }
        }
        
        const timeAgo = getTimeAgo(notif.created_at);
        const unreadClass = isRead ? '' : 'unread';
        
        html += `
            <div class="notification-item-header ${unreadClass}" data-notification-id="${notif.id}" data-request-id="${requestId}">
                <div class="notification-content">
                    <i class="fas ${iconClass} notification-icon"></i>
                    <div class="notification-text">
                        <div class="notification-title">${escapeHtml(title)}</div>
                        <div class="notification-message">${escapeHtml(message)}</div>
                        <div class="notification-time">${timeAgo}</div>
                        ${actionUrl ? `
                            <div class="notification-actions">
                                <a href="${actionUrl}" class="btn btn-sm btn-primary">${actionText}</a>
                                ${!isRead ? `<button class="btn btn-sm btn-link text-muted p-0" onclick="markNotificationRead(${notif.id}, event)">Mark as read</button>` : ''}
                            </div>
                        ` : (!isRead ? `<button class="btn btn-sm btn-link text-muted p-0 mt-2" onclick="markNotificationRead(${notif.id}, event)">Mark as read</button>` : '')}
                    </div>
                </div>
            </div>
        `;
    });
    
    // Render for both desktop and mobile
    if (notificationList) notificationList.innerHTML = html;
    if (notificationListMobile) notificationListMobile.innerHTML = html;
}

function markNotificationRead(notificationId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    fetch('assets/lib/emergency-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_notification_read&notification_id=${notificationId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remove unread class and update badge
            const notifItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notifItem) {
                notifItem.classList.remove('unread');
            }
            // Reload notifications to update count
            loadNotifications();
        }
    })
    .catch(err => console.error('Error marking notification as read:', err));
}

function markAllNotificationsRead() {
    fetch('assets/lib/emergency-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_notifications_read'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(err => console.error('Error marking all notifications as read:', err));
}

function showNotificationError() {
    const notificationList = document.getElementById('notificationList');
    const notificationListMobile = document.getElementById('notificationListMobile');
    
    [notificationList, notificationListMobile].forEach(list => {
        if (list) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <p class="mb-0">Failed to load notifications</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="loadNotifications()">Retry</button>
                </div>
            `;
        }
    });
}

function startNotificationAutoRefresh() {
    // Clear any existing interval
    if (notificationRefreshInterval) {
        clearInterval(notificationRefreshInterval);
    }
    
    // Set up new interval
    notificationRefreshInterval = setInterval(() => {
        // Only refresh if user is on the page (not in background tab)
        if (!document.hidden) {
            loadNotifications();
        }
    }, NOTIFICATION_REFRESH_INTERVAL);
}

function stopNotificationAutoRefresh() {
    if (notificationRefreshInterval) {
        clearInterval(notificationRefreshInterval);
        notificationRefreshInterval = null;
    }
}

// Stop auto-refresh when page is hidden, resume when visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopNotificationAutoRefresh();
    } else {
        startNotificationAutoRefresh();
        loadNotifications(); // Refresh immediately when page becomes visible
    }
});

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getTimeAgo(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    
    // Format date for older notifications
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}

