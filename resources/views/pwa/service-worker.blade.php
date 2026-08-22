const CACHE_NAME = '{{ $cacheVersion }}';
const STATIC_ASSETS = [
    '/images/pwa/icon-192.png',
    '/images/pwa/icon-512.png',
    '/offline',
];

const isPublicNavigation = (request, url) => request.mode === 'navigate'
    && request.method === 'GET'
    && url.origin === self.location.origin
    && !url.pathname.startsWith('/admin')
    && !url.pathname.startsWith('/account')
    && !url.pathname.startsWith('/leader-hub')
    && !url.pathname.startsWith('/login')
    && !url.pathname.startsWith('/register')
    && !url.pathname.startsWith('/password')
    && !url.pathname.startsWith('/photos/upload')
    && !url.pathname.startsWith('/media/')
    && !url.pathname.includes('/image/')
    && !url.pathname.includes('/download')
    && !url.pathname.startsWith('/api/');

const isImmutableStaticAsset = (request, url) => request.method === 'GET'
    && url.origin === self.location.origin
    && (url.pathname.startsWith('/build/') || STATIC_ASSETS.includes(url.pathname));

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key.startsWith('waymark-') && key !== CACHE_NAME).map((key) => caches.delete(key)),
    )));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (isImmutableStaticAsset(request, url)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok) caches.open(CACHE_NAME).then((cache) => cache.put(request, response.clone()));
            return response;
        })));
        return;
    }

    if (isPublicNavigation(request, url)) {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
    }
});
