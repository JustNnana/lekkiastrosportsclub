/**
 * Push Notifications Manager
 * Reads window.LASC_BASE_URL (set by footer.php) for dynamic API paths.
 */
class PushNotificationsManager {
    constructor() {
        this.vapidPublicKey = null;
        this.isSubscribed = false;
        this.swRegistration = null;
        // Use BASE_URL injected by PHP, fallback to root
        this.baseUrl = (window.LASC_BASE_URL || '/').replace(/\/?$/, '/');
    }

    /**
     * Initialize push notifications
     */
    async init() {
        // Check if service workers and push are supported
        if (!('serviceWorker' in navigator)) {
            console.warn('Service Workers are not supported');
            return { success: false, error: 'SERVICE_WORKER_NOT_SUPPORTED' };
        }
        
        if (!('PushManager' in window)) {
            console.warn('Push notifications are not supported');
            return { success: false, error: 'PUSH_NOT_SUPPORTED' };
        }

        try {
            // Wait for service worker to be ready
            this.swRegistration = await navigator.serviceWorker.ready;
            console.log('Service Worker is ready');
            
            // Get VAPID public key from server
            console.log('Fetching VAPID public key...');
            const response = await fetch(this.baseUrl + 'api/get-vapid-public-key.php');
            
            if (!response.ok) {
                throw new Error(`Failed to fetch VAPID key: ${response.status} ${response.statusText}`);
            }
            
            this.vapidPublicKey = await response.text();
            console.log('VAPID public key received:', this.vapidPublicKey ? 'Yes' : 'No');
            console.log('VAPID key length:', this.vapidPublicKey ? this.vapidPublicKey.length : 0);
            
            // Validate VAPID key
            if (!this.vapidPublicKey || this.vapidPublicKey.trim() === '') {
                throw new Error('VAPID public key is empty');
            }
            
            // Trim any whitespace
            this.vapidPublicKey = this.vapidPublicKey.trim();
            
            // Check current subscription status
            const subscription = await this.swRegistration.pushManager.getSubscription();
            this.isSubscribed = subscription !== null;
            
            console.log('Push notifications initialized. Subscribed:', this.isSubscribed);
            return { success: true };
        } catch (error) {
            console.error('Failed to initialize push notifications:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Subscribe to push notifications
     */
    async subscribe(userId) {
        try {
            // Check if initialized
            if (!this.vapidPublicKey) {
                return {
                    success: false,
                    error: 'NOT_INITIALIZED',
                    message: 'Push notifications not initialized. Please refresh the page and try again.'
                };
            }

            // Check if HTTPS or localhost
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                return {
                    success: false,
                    error: 'HTTPS_REQUIRED',
                    message: 'Push notifications require HTTPS. Please access the site using https://'
                };
            }

            // Request notification permission
            console.log('Requesting notification permission...');
            const permission = await Notification.requestPermission();
            
            console.log('Permission result:', permission);
            
            if (permission === 'denied') {
                return {
                    success: false,
                    error: 'PERMISSION_DENIED',
                    message: 'Notification permission was denied. Please enable notifications in your browser settings:\n\n1. Click the lock icon in the address bar\n2. Find "Notifications"\n3. Change to "Allow"\n4. Refresh the page and try again'
                };
            }
            
            if (permission !== 'granted') {
                return {
                    success: false,
                    error: 'PERMISSION_NOT_GRANTED',
                    message: 'Notification permission was not granted'
                };
            }

            // Convert VAPID key to Uint8Array
            console.log('Converting VAPID key...');
            let convertedVapidKey;
            try {
                convertedVapidKey = this.urlBase64ToUint8Array(this.vapidPublicKey);
                console.log('VAPID key converted successfully');
            } catch (error) {
                console.error('Error converting VAPID key:', error);
                return {
                    success: false,
                    error: 'VAPID_KEY_CONVERSION_ERROR',
                    message: 'Error converting VAPID key: ' + error.message
                };
            }

            // Subscribe to push notifications
            console.log('Subscribing to push manager...');
            const subscription = await this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            console.log('Push subscription created');

            // Save subscription to server
            console.log('Saving subscription to server...');
            const response = await fetch(this.baseUrl + 'api/save-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    user_id: userId
                })
            });

            if (!response.ok) {
                throw new Error(`Server returned ${response.status}`);
            }

            const result = await response.json();
            console.log('Server response:', result);
            
            if (result.success) {
                this.isSubscribed = true;
                return {
                    success: true,
                    message: 'Push notifications enabled successfully!'
                };
            } else {
                return {
                    success: false,
                    error: 'SERVER_ERROR',
                    message: 'Failed to save subscription: ' + result.message
                };
            }
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            return {
                success: false,
                error: 'SUBSCRIPTION_ERROR',
                message: 'Error: ' + error.message
            };
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe(userId) {
        try {
            const subscription = await this.swRegistration.pushManager.getSubscription();
            
            if (!subscription) {
                console.log('No subscription found');
                return { success: true };
            }

            // Unsubscribe from push notifications
            await subscription.unsubscribe();

            // Delete subscription from server
            const response = await fetch(this.baseUrl + 'api/delete-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    user_id: userId
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.isSubscribed = false;
                return {
                    success: true,
                    message: 'Push notifications disabled successfully'
                };
            } else {
                return {
                    success: false,
                    error: 'SERVER_ERROR',
                    message: 'Failed to delete subscription: ' + result.message
                };
            }
        } catch (error) {
            console.error('Error unsubscribing from push notifications:', error);
            return {
                success: false,
                error: 'UNSUBSCRIBE_ERROR',
                message: 'Error: ' + error.message
            };
        }
    }

    /**
     * Convert VAPID key from base64 to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        // Validate input
        if (!base64String) {
            throw new Error('VAPID key is null or undefined');
        }
        
        if (typeof base64String !== 'string') {
            throw new Error('VAPID key must be a string');
        }
        
        if (base64String.trim() === '') {
            throw new Error('VAPID key is empty');
        }
        
        try {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        } catch (error) {
            throw new Error('Failed to decode VAPID key: ' + error.message);
        }
    }

    /**
     * Check notification permission status
     */
    getPermissionStatus() {
        if (!('Notification' in window)) {
            return 'unsupported';
        }
        return Notification.permission;
    }
}

// Initialize global push notifications manager
const pushNotifications = new PushNotificationsManager();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing push notifications...');
    const initResult = await pushNotifications.init();
    
    if (!initResult.success) {
        console.error('Push notifications initialization failed:', initResult.error);
    }
    
    // Initialize toggle button if it exists
    const toggleButton = document.getElementById('push-notification-toggle');
    if (toggleButton) {
        // Check actual subscription status from server
        const checkSubscriptionStatus = async () => {
            try {
                const subscription = await pushNotifications.swRegistration.pushManager.getSubscription();
                const isActuallySubscribed = subscription !== null;

                // Update the toggle to match actual state
                toggleButton.checked = isActuallySubscribed;
                pushNotifications.isSubscribed = isActuallySubscribed;

                console.log('Subscription status checked:', isActuallySubscribed);

                // Enable/disable test button
                const testButton = document.getElementById('test-push-notification');
                if (testButton) {
                    testButton.disabled = !isActuallySubscribed;
                }

                // Update status indicator
                updateNotificationStatus(isActuallySubscribed);
            } catch (error) {
                console.error('Error checking subscription status:', error);
                toggleButton.checked = false;
            }
        };

        // Check status on load
        checkSubscriptionStatus();

        // Show current permission status
        const permissionStatus = pushNotifications.getPermissionStatus();
        console.log('Current notification permission:', permissionStatus);

        // Handle toggle
        toggleButton.addEventListener('change', async (e) => {
            const userId = toggleButton.dataset.userId;

            if (!userId) {
                console.error('User ID not found');
                e.target.checked = !e.target.checked;
                alert('Error: User ID not found. Please refresh the page.');
                return;
            }

            // Show loading state
            toggleButton.disabled = true;
            const statusIndicator = document.getElementById('notification-status');
            if (statusIndicator) {
                statusIndicator.style.display = 'block';
                statusIndicator.style.background = '#f0f0f0';
                statusIndicator.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
            }

            if (e.target.checked) {
                const result = await pushNotifications.subscribe(parseInt(userId));

                if (result.success) {
                    updateNotificationStatus(true, result.message);

                    // Enable test button
                    const testButton = document.getElementById('test-push-notification');
                    if (testButton) {
                        testButton.disabled = false;
                    }
                } else {
                    e.target.checked = false;
                    updateNotificationStatus(false, result.message, true);
                }
            } else {
                const result = await pushNotifications.unsubscribe(parseInt(userId));

                if (result.success) {
                    updateNotificationStatus(false, result.message || 'Push notifications disabled');

                    // Disable test button
                    const testButton = document.getElementById('test-push-notification');
                    if (testButton) {
                        testButton.disabled = true;
                    }
                } else {
                    e.target.checked = true;
                    updateNotificationStatus(true, result.message, true);
                }
            }

            // Remove loading state
            toggleButton.disabled = false;
        });
    }

    // Function to update notification status indicator
    function updateNotificationStatus(isEnabled, message = '', isError = false) {
        const statusIndicator = document.getElementById('notification-status');
        if (!statusIndicator) return;

        statusIndicator.style.display = 'block';

        if (isError) {
            statusIndicator.style.background = '#f8d7da';
            statusIndicator.style.color = '#721c24';
            statusIndicator.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i> ${message}`;
        } else if (isEnabled) {
            statusIndicator.style.background = '#d4edda';
            statusIndicator.style.color = '#155724';
            statusIndicator.innerHTML = `<i class="fas fa-check-circle me-2"></i> ${message || 'Push notifications are enabled'}`;
        } else {
            statusIndicator.style.background = '#fff3cd';
            statusIndicator.style.color = '#856404';
            statusIndicator.innerHTML = `<i class="fas fa-info-circle me-2"></i> ${message || 'Push notifications are disabled'}`;
        }

        // Auto-hide success/info messages after 5 seconds
        if (!isError) {
            setTimeout(() => {
                statusIndicator.style.display = 'none';
            }, 5000);
        }
    }
});