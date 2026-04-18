// Bump the cache name whenever this file changes so activated clients
// drop stale caches (old login HTML with stale CSRF tokens, etc.).
const CACHE_NAME = 'lms-v2';
const OFFLINE_URL = '/lms/';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll([OFFLINE_URL]))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Auth pages must never be served from cache — they carry a per-session
// CSRF token baked into @csrf, and a stale token means "Page Expired" (419)
// on submit.
const AUTH_PATH_RE = /\/(login|register|logout)(\/|$|\?)/i;

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;

    const url = new URL(event.request.url);

    // Always go to network for auth flows — never fall back to the offline
    // shell either (a cached /lms/ redirect could mask a real auth failure).
    if (AUTH_PATH_RE.test(url.pathname)) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
});
