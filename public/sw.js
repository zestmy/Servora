// Servora main app service worker. Kept deliberately conservative:
// - Navigation: network-first with an offline fallback to /offline
// - Auth paths and Livewire traffic: always bypass cache (no stale CSRF tokens)
// - Non-navigation GETs: pass through (don't pollute cache with API/JSON)
const CACHE_NAME = 'servora-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll([OFFLINE_URL]).catch(() => null))
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

// Any URL that carries per-session CSRF state or handles auth must never be
// served from cache. Hitting a stale login HTML or a cached Livewire response
// produces "Page Expired" (419) on submit.
const BYPASS_RE = /\/(login|register|logout|password|livewire)(\/|$|\?)/i;

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only intervene on GET navigation requests. Leave XHR/Livewire/assets alone.
    if (req.method !== 'GET') return;
    if (req.mode !== 'navigate') return;

    const url = new URL(req.url);
    if (BYPASS_RE.test(url.pathname)) {
        event.respondWith(fetch(req));
        return;
    }

    event.respondWith(
        fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
});
