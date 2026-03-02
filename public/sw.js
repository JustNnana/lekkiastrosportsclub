/**
 * Lekki Astro Sports Club — Service Worker
 * Caches key assets for offline support (Week 5 of plan will expand this)
 */

const CACHE_NAME = 'lasc-v1';

const STATIC_ASSETS = [
    '/',
    '/assets/css/dasher-variables.css',
    '/assets/css/dasher-core-styles.css',
    '/assets/css/dasher-table-chart-styles.css',
    '/assets/js/dasher-theme-system.js',
    '/assets/images/icons/icon-192x192.png',
    '/assets/images/icons/icon-512x512.png',
    '/manifest.json'
];

// Install: cache static assets
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clear old caches
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: network-first for API/PHP, cache-first for static assets
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);

    // Skip non-GET and cross-origin requests
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;

    // API / PHP — network only
    if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')) {
        e.respondWith(fetch(e.request));
        return;
    }

    // Static assets — cache first, fallback to network
    e.respondWith(
        caches.match(e.request).then((cached) =>
            cached || fetch(e.request).then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(e.request, clone));
                }
                return response;
            })
        )
    );
});

// ─── PUSH EVENT ────────────────────────────────────────────────────────────
self.addEventListener('push', (e) => {
    let data = { title: 'Lekki Astro SC', body: 'You have a new notification.' };

    if (e.data) {
        try {
            data = e.data.json();
        } catch {
            data.body = e.data.text();
        }
    }

    // Derive base URL from SW scope so it works on any domain/path
    const base = self.registration.scope;

    const options = {
        body:    data.body    || '',
        icon:    data.icon    || (base + 'assets/images/icons/icon-192x192.png'),
        badge:   data.badge   || (base + 'assets/images/icons/badge-96x96.png'),
        tag:     data.tag     || 'lasc-notification',
        data:    { url: data.url || (base + 'notifications/index.php') },
        vibrate: [100, 50, 100],
        requireInteraction: false,
    };

    e.waitUntil(self.registration.showNotification(data.title, options));
});

// ─── NOTIFICATION CLICK ────────────────────────────────────────────────────
self.addEventListener('notificationclick', (e) => {
    e.notification.close();

    const targetUrl = e.notification.data?.url || self.registration.scope;

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client) client.navigate(targetUrl);
                    return;
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});
