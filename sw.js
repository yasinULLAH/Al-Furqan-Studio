const CACHE_NAME = 'nur-ul-quran-cache-v1';
const MANIFEST_URL = './info.csv';

const parseManifest = async () => {
    try {
        const response = await fetch(MANIFEST_URL);
        if (!response.ok) {
            throw new Error('Network response for manifest was not ok');
        }
        const text = await response.text();
        const lines = text.trim().split('\n');
        const headers = lines.shift().trim().split(',').map(h => h.trim());
        const urlIndex = headers.indexOf('url');
        if (urlIndex === -1) {
            throw new Error('Manifest CSV must contain a "url" header.');
        }
        return lines.map(line => {
            const values = line.trim().split(',');
            return values[urlIndex] ? values[urlIndex].trim() : null;
        }).filter(Boolean);
    } catch (error) {
        console.error('Failed to fetch or parse manifest:', error);
        return [];
    }
};

self.addEventListener('install', event => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const appShellFiles = ['./index.html', MANIFEST_URL];
            const dataFiles = await parseManifest();
            const allFilesToCache = [...appShellFiles, ...dataFiles];
            await cache.addAll(allFilesToCache);
        })()
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', event => {
    if (!event.request.url.startsWith('http')) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request).then(
                networkResponse => {
                    if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
                        return networkResponse;
                    }

                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseToCache);
                    });

                    return networkResponse;
                }
            );
        })
    );
});