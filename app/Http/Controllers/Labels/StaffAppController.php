<?php

namespace App\Http\Controllers\Labels;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * PWA plumbing for the staff label app.
 *
 * Served through Laravel rather than as static files because the app's base
 * path differs by environment — /labels behind a company subdomain in
 * production, /labels-staff locally — and both the manifest's start_url and
 * the service worker's scope have to match wherever it is mounted.
 *
 * A controller, not route closures: deploy/update.sh runs `route:cache`,
 * and closures cannot be cached.
 */
class StaffAppController extends Controller
{
    public function manifest(): Response
    {
        $start   = route('labels.staff.print', absolute: false);
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;
        $brand   = $company?->brand_name ?? $company?->name;

        $manifest = [
            // Branded in the install prompt, but short_name stays generic:
            // a home screen truncates to roughly twelve characters, so
            // "Dotty's Suria KLCC Labels" would read as "Dotty's Sur…".
            'name'             => $brand ? $brand . ' Labels' : 'Servora Labels',
            'short_name'       => 'Labels',
            'description'      => 'Print food safety labels and check what is expiring.',
            'start_url'        => $start,
            'scope'            => $this->scope(),
            'display'          => 'standalone',
            // Locks to portrait: this is used one-handed, and a rotation
            // mid-print reflows the queue under the chef's thumb.
            'orientation'      => 'portrait',
            'background_color' => '#f9fafb',
            'theme_color'      => '#0b7677',
            'icons'            => [
                [
                    'src'   => asset('labels-app/icon-192.png'),
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'   => asset('labels-app/icon-512.png'),
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    // Android crops icons to its own shape; this one has the
                    // glyph inside the safe zone so it survives the crop.
                    'src'     => asset('labels-app/icon-maskable-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Deliberately cautious.
     *
     * A label app must never serve stale shelf-life data or let someone
     * believe they printed while offline, so nothing dynamic is cached:
     * network-first for pages, with a plain offline notice if the network
     * is gone. Only the icons are cached outright.
     *
     * Livewire posts to /livewire/update, which sits OUTSIDE this worker's
     * scope, so interactions are never intercepted at all.
     */
    public function serviceWorker(): Response
    {
        $scope   = $this->scope();
        // Bumped when the cached assets change. The worker caches the
        // icons outright, so an installed app keeps serving the old ones
        // until the cache name moves and `activate` deletes the previous key.
        // v2: icons redrawn in brand teal (they were pre-rebrand indigo).
        $version = 'v2';
        $icons   = json_encode([
            asset('labels-app/icon-192.png'),
            asset('labels-app/icon-512.png'),
        ], JSON_UNESCAPED_SLASHES);

        $offline = addcslashes(
            '<!doctype html><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Offline</title>'
            . '<div style="font-family:system-ui;padding:2rem;text-align:center;color:#374151">'
            . '<h1 style="font-size:1.1rem">No connection</h1>'
            . '<p style="font-size:.875rem;color:#6b7280">Labels needs a connection to print. '
            . 'Reconnect and try again.</p></div>',
            "'\\"
        );

        $js = <<<JS
        const CACHE = 'servora-labels-{$version}';
        const ICONS = {$icons};
        const OFFLINE_HTML = '{$offline}';

        self.addEventListener('install', (event) => {
            event.waitUntil(caches.open(CACHE).then((c) => c.addAll(ICONS)).then(() => self.skipWaiting()));
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys()
                    .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
                    .then(() => self.clients.claim())
            );
        });

        self.addEventListener('fetch', (event) => {
            const req = event.request;

            // Never touch anything that changes state, and never cache a
            // Livewire round trip.
            if (req.method !== 'GET') {
                return;
            }

            // Page loads: always try the network so shelf lives and expiry
            // times are current. Only fall back to a notice.
            if (req.mode === 'navigate') {
                event.respondWith(
                    fetch(req).catch(() => new Response(OFFLINE_HTML, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                    }))
                );
                return;
            }

            // Icons and other same-origin static files: cache is fine.
            if (ICONS.some((i) => req.url.endsWith(i.split('/').pop()))) {
                event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
            }
        });
        JS;

        return response($js)
            ->header('Content-Type', 'application/javascript')
            // Lets the worker control the whole app path even though the
            // script itself is served from within it.
            ->header('Service-Worker-Allowed', $scope)
            ->header('Cache-Control', 'no-cache');
    }

    /** The app's base path, which differs between production and local. */
    private function scope(): string
    {
        $start = route('labels.staff.print', absolute: false);

        return rtrim($start, '/') . '/';
    }
}
