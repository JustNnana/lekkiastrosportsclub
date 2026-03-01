/**
 * Gate Wey Access Management System
 * Notifications Page - AJAX & Push Notification System
 * Location: /assets/js/notifications-ajax.js
 * 
 * ✅ UPDATED WITH:
 * - Duplicate notification prevention
 * - Smart URL generation
 * - "View" link only shows when URL exists
 * - Debug logging
 */

const NotificationsPage = {
    currentPage: 1,
    isLoading: false,
    autoRefreshInterval: null,
    autoRefreshDelay: 30000, // 30 seconds
    
    /**
     * Initialize the notifications system
     */
    init() {
        console.log('🔔 Initializing AJAX Notifications System...');
        
        // Load initial notifications
        this.loadNotifications();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Start auto-refresh
        this.startAutoRefresh();
        
        // Handle visibility change (pause when tab is not visible)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
                console.log('⏸️ Auto-refresh paused (tab hidden)');
            } else {
                this.startAutoRefresh();
                this.loadNotifications(); // Refresh immediately when tab becomes visible
                console.log('▶️ Auto-refresh resumed (tab visible)');
            }
        });
        
        console.log('✅ Notifications system initialized');
    },
    
    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Filter form submission
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.currentPage = 1;
                this.loadNotifications();
            });
            
            // Auto-apply filters on change
            filterForm.querySelectorAll('select').forEach(select => {
                select.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.loadNotifications();
                });
            });
        }
        
        // Mark all as read button
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.markAllAsRead();
            });
        }
        
        // Delete all button
        const deleteAllBtn = document.getElementById('deleteAllBtn');
        if (deleteAllBtn) {
            deleteAllBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const message = 'Are you sure you want to delete ALL notifications? This action cannot be undone.';
                if (confirm(message)) {
                    this.deleteAll();
                }
            });
        }
        
        // Manual refresh button
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.loadNotifications();
                
                // Add spin animation
                const icon = refreshBtn.querySelector('i');
                if (icon) {
                    icon.classList.add('fa-spin');
                    setTimeout(() => icon.classList.remove('fa-spin'), 1000);
                }
            });
        }
    },
    
    /**
     * Load notifications via AJAX
     */
    async loadNotifications() {
        if (this.isLoading) {
            console.log('⏳ Already loading notifications...');
            return;
        }
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            // Get filter values
            const filterForm = document.getElementById('filterForm');
            const formData = filterForm ? new FormData(filterForm) : new FormData();
            
            // Build query string
            const params = new URLSearchParams();
            params.append('page', this.currentPage);
            
            if (formData.get('filter')) params.append('filter', formData.get('filter'));
            if (formData.get('type')) params.append('type', formData.get('type'));
            if (formData.get('clan_id')) params.append('clan_id', formData.get('clan_id'));
            
            console.log('📡 Fetching notifications...', params.toString());
            
            const response = await fetch(`/api/get-notifications.php?${params.toString()}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('✅ Notifications loaded:', data);
            
            if (data.success) {
                this.renderNotifications(data);
                this.updateStats(data.stats);
            } else {
                this.showError(data.message || 'Failed to load notifications');
            }
        } catch (error) {
            console.error('❌ Load notifications error:', error);
            this.showError('Failed to load notifications. Please try again.');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    },
    
    /**
     * Render notifications to the DOM
     * ✅ UPDATED: Now prevents duplicate notifications
     */
    renderNotifications(data) {
        const container = document.getElementById('notificationsContainer');
        if (!container) {
            console.error('❌ Notifications container not found');
            return;
        }
        
        const { notifications, pagination } = data;
        
        // Show empty state if no notifications
        if (notifications.length === 0) {
            container.innerHTML = this.getEmptyStateHTML();
            this.renderPagination(pagination);
            return;
        }
        
        // ============================================
        // FIX: Prevent duplicate notifications
        // ============================================
        const renderedIds = new Set();
        const uniqueNotifications = [];
        
        notifications.forEach(notification => {
            // Skip if already rendered
            if (!renderedIds.has(notification.id)) {
                renderedIds.add(notification.id);
                uniqueNotifications.push(notification);
            } else {
                console.warn('⚠️ Duplicate notification detected and skipped:', notification.id);
            }
        });
        
        console.log(`✅ Rendering ${uniqueNotifications.length} unique notifications (${notifications.length - uniqueNotifications.length} duplicates removed)`);
        // ============================================
        
        // Build notifications HTML from unique notifications only
        let html = '';
        uniqueNotifications.forEach(notification => {
            html += this.getNotificationHTML(notification);
        });
        
        container.innerHTML = html;
        this.renderPagination(pagination);
        
        // Attach event listeners to notification actions
        this.attachNotificationListeners();
        
        // Animate new notifications
        this.animateNotifications();
    },
    
    /**
     * Generate HTML for a single notification
     * ✅ UPDATED: iOS-style list items (matching my-events.php)
     */
    getNotificationHTML(notification) {
        const iconClass = this.getNotificationIconClass(notification.type);
        const isUnread = !notification.is_read;

        // Get notification URL
        const viewUrl = this.getNotificationUrl(notification);

        // Build the notification item (matching my-events.php structure)
        return `
            <div class="ios-notification-item ${isUnread ? 'unread' : ''}" data-id="${notification.id}" ${viewUrl ? `onclick="window.location.href='${viewUrl}'"` : ''}>
                <span class="ios-notification-dot ${iconClass}"></span>
                <div class="ios-notification-content">
                    <p class="ios-notification-title">${this.escapeHtml(notification.title)}</p>
                    <p class="ios-notification-message">${this.escapeHtml(notification.message)}</p>
                    <p class="ios-notification-datetime">
                        <i class="fas fa-clock"></i> ${this.formatDate(notification.created_at)}
                        ${notification.clan_name ? ` &bull; <i class="fas fa-users"></i> ${this.escapeHtml(notification.clan_name)}` : ''}
                    </p>
                </div>
                <div class="ios-notification-meta">
                    ${notification.type ? `
                        <span class="ios-notification-type-badge ${iconClass}">
                            ${this.formatType(notification.type)}
                        </span>
                    ` : ''}
                    ${isUnread ? `
                        <span class="ios-notification-status unread">
                            <i class="fas fa-circle"></i> New
                        </span>
                    ` : `
                        <span class="ios-notification-status read">
                            <i class="fas fa-check"></i> Read
                        </span>
                    `}
                    <div class="ios-notification-actions">
                        ${isUnread ? `
                            <button class="ios-action-btn mark-read-btn" data-id="${notification.id}" title="Mark as Read" onclick="event.stopPropagation();">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        <button class="ios-action-btn danger delete-btn" data-id="${notification.id}" title="Delete" onclick="event.stopPropagation();">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <i class="fas fa-chevron-right ios-notification-chevron"></i>
            </div>
        `;
    },
    
    /**
     * Generate empty state HTML - iOS Style
     */
    getEmptyStateHTML() {
        return `
            <div class="ios-empty-state">
                <div class="ios-empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h5 class="ios-empty-title">No Notifications Found</h5>
                <p class="ios-empty-text">You don't have any notifications matching your current filters.</p>
            </div>
        `;
    },
    
    /**
     * Render pagination controls - iOS Style
     */
    renderPagination(pagination) {
        const paginationContainer = document.getElementById('paginationContainer');
        if (!paginationContainer) return;

        if (pagination.total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = `
            <div class="ios-pagination">
                <div class="ios-pagination-info">
                    ${pagination.offset + 1}-${Math.min(pagination.offset + pagination.per_page, pagination.total_count)} of ${pagination.total_count}
                </div>
                <div class="ios-pagination-nav">
                    <button class="ios-page-btn" data-page="${pagination.current_page - 1}" ${pagination.current_page <= 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i>
                    </button>
        `;

        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="ios-page-btn ${i === pagination.current_page ? 'active' : ''}" data-page="${i}">${i}</button>
            `;
        }

        html += `
                    <button class="ios-page-btn" data-page="${pagination.current_page + 1}" ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        `;

        paginationContainer.innerHTML = html;

        // Attach pagination listeners
        paginationContainer.querySelectorAll('.ios-page-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(btn.getAttribute('data-page'));
                if (page && page !== this.currentPage && page >= 1 && page <= pagination.total_pages) {
                    this.currentPage = page;
                    this.loadNotifications();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    },
    
    /**
     * Attach event listeners to notification action buttons
     */
    attachNotificationListeners() {
        // Mark as read buttons
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.getAttribute('data-id');
                this.markAsRead(id);
            });
        });
        
        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this notification?')) {
                    this.deleteNotification(id);
                }
            });
        });
    },
    
    /**
     * Mark a single notification as read
     */
    async markAsRead(notificationId) {
        try {
            console.log('📖 Marking notification as read:', notificationId);
            
            const response = await fetch('/api/notification-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: parseInt(notificationId)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadNotifications();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('❌ Mark as read error:', error);
            this.showError('Failed to mark notification as read');
        }
    },
    
    /**
     * Mark all notifications as read
     */
    async markAllAsRead() {
        try {
            console.log('📖 Marking all notifications as read...');
            
            const filterForm = document.getElementById('filterForm');
            const formData = filterForm ? new FormData(filterForm) : new FormData();
            
            const response = await fetch('/api/notification-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'mark_all_read',
                    clan_id: formData.get('clan_id') || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadNotifications();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('❌ Mark all as read error:', error);
            this.showError('Failed to mark all notifications as read');
        }
    },
    
    /**
     * Delete a single notification
     */
    async deleteNotification(notificationId) {
        try {
            console.log('🗑️ Deleting notification:', notificationId);
            
            const response = await fetch('/api/notification-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'delete',
                    notification_id: parseInt(notificationId)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadNotifications();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('❌ Delete notification error:', error);
            this.showError('Failed to delete notification');
        }
    },
    
    /**
     * Delete all notifications
     */
    async deleteAll() {
        try {
            console.log('🗑️ Deleting all notifications...');
            
            const filterForm = document.getElementById('filterForm');
            const formData = filterForm ? new FormData(filterForm) : new FormData();
            
            const response = await fetch('/api/notification-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'delete_all',
                    clan_id: formData.get('clan_id') || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadNotifications();
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('❌ Delete all error:', error);
            this.showError('Failed to delete all notifications');
        }
    },
    
    /**
     * Update statistics display
     */
    updateStats(stats) {
        const totalElement = document.getElementById('totalNotifications');
        const unreadElement = document.getElementById('unreadNotifications');
        
        if (totalElement) {
            totalElement.textContent = stats.total_count.toLocaleString();
        }
        if (unreadElement) {
            unreadElement.textContent = stats.unread_count.toLocaleString();
        }
    },
    
    /**
     * Start auto-refresh interval
     */
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.autoRefreshInterval = setInterval(() => {
            console.log('🔄 Auto-refreshing notifications...');
            this.loadNotifications();
        }, this.autoRefreshDelay);
        console.log('✅ Auto-refresh started (every 30 seconds)');
    },
    
    /**
     * Stop auto-refresh interval
     */
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    },
    
    /**
     * Animate notifications on load
     */
    animateNotifications() {
        const items = document.querySelectorAll('.ios-notification-item');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(10px)';
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 50);
        });
    },

    /**
     * Show loading state - iOS Style
     */
    showLoading() {
        const container = document.getElementById('notificationsContainer');
        if (!container) return;

        // Only show loading on first load or if container is empty
        if (container.querySelector('.ios-empty-state') || container.children.length === 0) {
            container.innerHTML = `
                <div class="ios-empty-state">
                    <div class="ios-empty-icon">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <h5 class="ios-empty-title">Loading notifications...</h5>
                </div>
            `;
        }
    },
    
    /**
     * Hide loading state
     */
    hideLoading() {
        // Loading is hidden when content is rendered
    },
    
    /**
     * Show success toast message
     */
    showSuccess(message) {
        this.showToast(message, 'success');
    },
    
    /**
     * Show error toast message
     */
    showError(message) {
        this.showToast(message, 'error');
    },
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} notification-toast`;
        toast.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px; animation: slideInRight 0.3s ease; box-shadow: var(--shadow-lg);';
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <div class="alert-content">
                <div class="alert-message">${this.escapeHtml(message)}</div>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
    
    // ==========================================
    // UTILITY FUNCTIONS
    // ==========================================
    
    /**
     * ✅ NEW: Generate proper URL for notification based on type and reference
     */
    getNotificationUrl(notification) {
        const BASE_URL = window.location.origin + '/';
        
        // Check if view_url is provided by backend
        if (notification.view_url) {
            console.log(`✅ Using backend URL for notification ${notification.id}:`, notification.view_url);
            return notification.view_url;
        }
        
        // Check if URL is in reference_data
        if (notification.reference_data && notification.reference_data.url) {
            console.log(`✅ Using reference_data URL for notification ${notification.id}:`, notification.reference_data.url);
            return notification.reference_data.url;
        }
        
        // Generate URL based on notification type
        const refType = notification.reference_type;
        const refId = notification.reference_id;
        const type = notification.type;
        
        let generatedUrl = null;
        
        // Access code related
        if (refType === 'access_code' && refId) {
            generatedUrl = BASE_URL + 'access-codes/view.php?id=' + refId;
        }
        // Payment related
        else if (type && type.indexOf('payment_') === 0) {
            if (refId && refType === 'payment') {
                generatedUrl = BASE_URL + 'payments/receipt.php?id=' + refId;
            } else {
                generatedUrl = BASE_URL + 'payments/';
            }
        }
        // User related
        else if (refType === 'user' && refId) {
            generatedUrl = BASE_URL + 'users/view.php?id=' + refId;
        }
        // Clan related
        else if (refType === 'clan' && refId) {
            generatedUrl = BASE_URL + 'clans/view.php?id=' + refId;
        }
        // Chat related
        else if (refType === 'chat' && refId) {
            generatedUrl = BASE_URL + 'chat/?id=' + refId;
        }
        // Marketplace related
        else if (refType === 'marketplace' && refId) {
            generatedUrl = BASE_URL + 'marketplace/product.php?id=' + refId;
        }
        // Event related
        else if (refType === 'event' && refId) {
            generatedUrl = BASE_URL + 'user/events/view-event.php?id=' + refId;
        }

        if (generatedUrl) {
            console.log(`✅ Generated URL for notification ${notification.id} (Type: ${type}, RefType: ${refType}, RefID: ${refId}):`, generatedUrl);
        } else {
            console.log(`⚠️ No URL generated for notification ${notification.id} (Type: ${type}, RefType: ${refType}, RefID: ${refId})`);
        }
        
        return generatedUrl;
    },
    
    /**
     * Get notification icon (FontAwesome icon name) based on type
     */
    getNotificationIcon(type) {
        if (!type) return 'bell';
        if (type.includes('payment')) return 'credit-card';
        if (type.includes('event_cancelled')) return 'calendar-times';
        if (type.includes('event_approved')) return 'calendar-check';
        if (type.includes('event_rejected')) return 'calendar-xmark';
        if (type.includes('event')) return 'calendar-alt';
        if (type.includes('access_code')) return 'key';
        if (type.includes('user') || type.includes('registration')) return 'user';
        if (type.includes('clan')) return 'users';
        if (type.includes('chat')) return 'comment';
        if (type.includes('announcement')) return 'bullhorn';
        return 'bell';
    },

    /**
     * Get notification icon class based on type
     */
    getNotificationIconClass(type) {
        if (!type) return 'primary';
        if (type.includes('success') || type.includes('processed') || type.includes('approved')) return 'success';
        if (type.includes('failed') || type.includes('cancelled') || type.includes('rejected')) return 'danger';
        if (type.includes('reminder') || type.includes('refund') || type.includes('pending')) return 'warning';
        if (type.includes('overdue')) return 'info';
        if (type.includes('event')) return 'primary';
        return 'primary';
    },
    
    /**
     * Get notification badge class based on type
     */
    getNotificationBadgeClass(type) {
        if (!type) return '';
        if (type.includes('success') || type.includes('processed') || type.includes('approved')) return 'type-success';
        if (type.includes('failed') || type.includes('cancelled') || type.includes('rejected')) return 'type-danger';
        if (type.includes('reminder') || type.includes('refund') || type.includes('pending')) return 'type-warning';
        if (type.includes('overdue')) return 'type-info';
        if (type.includes('event')) return 'type-primary';
        return '';
    },
    
    /**
     * Format notification type for display
     */
    formatType(type) {
        if (!type) return '';
        return type.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    },
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        // Show relative time for recent notifications
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        // Otherwise show formatted date
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    },
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// ==========================================
// ADD CSS FOR ANIMATIONS
// ==========================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    .notification-toast {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: var(--border-radius);
    }
    
    .notification-toast i {
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    
    .notification-toast .alert-content {
        flex: 1;
    }
    
    .notification-toast .alert-message {
        margin: 0;
        font-size: var(--font-size-sm);
    }
    
    .notification-link {
        color: var(--primary) !important;
        text-decoration: none;
        transition: var(--transition-fast);
        font-weight: var(--font-weight-medium);
    }
    
    .notification-link:hover {
        color: var(--primary-dark) !important;
        text-decoration: underline;
    }
`;
document.head.appendChild(style);

// ==========================================
// INITIALIZE WHEN DOM IS READY
// ==========================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NotificationsPage.init());
} else {
    NotificationsPage.init();
}

// Export for use in other scripts if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationsPage;
}