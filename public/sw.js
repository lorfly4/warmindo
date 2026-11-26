
const CACHE_NAME = 'warmindo-pwa-v1';

const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.add(OFFLINE_URL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) =>
                        key.startsWith('warmindo-pwa-') &&
                        key !== CACHE_NAME
                    )
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Jangan intervensi request selain GET atau lintas domain.
    if (
        request.method !== 'GET' ||
        url.origin !== self.location.origin
    ) {
        return;
    }

    // Navigasi halaman: selalu utamakan server.
    // Jangan menyimpan halaman transaksi atau dashboard.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                return await caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // Cache hanya aset statis.
    const staticTypes = [
        'style',
        'script',
        'image',
        'font'
    ];

    if (staticTypes.includes(request.destination)) {
        event.respondWith(
            caches.match(request).then(async (cached) => {
                if (cached) {
                    return cached;
                }

                try {
                    const response = await fetch(request);

                    if (response.ok && response.type === 'basic') {
                        const cache = await caches.open(CACHE_NAME);
                        await cache.put(request, response.clone());
                    }

                    return response;
                } catch (error) {
                    return Response.error();
                }
            })
        );
    }
});
