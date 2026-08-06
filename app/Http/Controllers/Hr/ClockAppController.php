<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * PWA plumbing for the staff clock-in app.
 *
 * Served through Laravel rather than as static files because the app's base
 * path is derived from the routes — /staff behind a company subdomain — and
 * both the manifest's start_url and the service worker's scope have to match
 * wherever it is mounted.
 *
 * A controller, not route closures: deploy/update.sh runs `route:cache`,
 * and closures cannot be cached.
 */
class ClockAppController extends Controller
{
    public function manifest(): Response
    {
        $start   = route('clock.staff.punch', absolute: false);
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;
        $brand   = $company?->brand_name ?? $company?->name;

        $manifest = [
            // Named for the whole app, not just the clock: leave and time off
            // live here too, and an icon labelled "Clock In" is the wrong
            // place to go looking for annual leave.
            'name'        => $brand ? $brand . ' Staff Portal' : 'Servora Staff Portal',
            // A home screen truncates around twelve characters, and this icon
            // is tapped at the door twice a day — it has to be unmistakable.
            'short_name'  => 'Staff Portal',
            'description' => 'Clock in, check your punches, and apply for leave.',
            'start_url'   => $start,
            'scope'       => $this->scope(),
            'display'     => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f9fafb',
            'theme_color'      => '#0b7677',
            /*
             * The company's own logo when they have uploaded one, else the
             * Servora mark. Not the clock glyph: the app holds leave and time
             * off now, and staff pick this icon off a home screen full of
             * others — their own brand is the fastest thing to find.
             *
             * A logo is declared WITHOUT `sizes`, because we do not know its
             * dimensions and claiming 512x512 for a 300px file makes Android
             * upscale it into something blurry. "any" lets the browser use it
             * at whatever size it needs.
             */
            'icons'            => $company?->logo
                ? [[
                    'src'     => \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo),
                    'sizes'   => 'any',
                    'purpose' => 'any',
                ]]
                : [
                    [
                        'src'     => asset('favicon.png'),
                        'sizes'   => '300x300',
                        'type'    => 'image/png',
                        'purpose' => 'any',
                    ],
                    [
                        // Android crops icons to its own shape; the maskable
                        // one keeps its glyph inside the safe zone.
                        'src'     => asset('clock-app/icon-maskable-512.png'),
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
     * Cautious about pages, generous about models.
     *
     * Nothing dynamic is cached: a clock-in screen that renders from cache
     * could show yesterday's shift, and an offline punch that appears to
     * succeed is worse than one that plainly fails — the employee would walk
     * away believing they had clocked in.
     *
     * The face model files are the exception and are cached hard. They are
     * roughly 6.5MB of immutable weights fetched before the first punch of
     * the day; re-downloading them on a kitchen's shared 4G every morning is
     * the difference between a two-second clock-in and a thirty-second one.
     * They are content-addressed by the version in the cache name, so a model
     * swap invalidates them wholesale rather than serving a stale mix.
     */
    public function serviceWorker(): Response
    {
        $scope   = $this->scope();
        $version = 'v1';
        $models  = asset('face-models') . '/';

        $offline = addcslashes(
            '<!doctype html><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Offline</title>'
            . '<div style="font-family:system-ui;padding:2rem;text-align:center;color:#374151">'
            . '<h1 style="font-size:1.1rem">No connection</h1>'
            . '<p style="font-size:.875rem;color:#6b7280">The Staff Portal needs a connection to record your punch. '
            . 'Reconnect and try again — do not assume you are clocked in.</p></div>',
            "'\\"
        );

        $js = <<<JS
        const CACHE = 'servora-clock-{$version}';
        const MODEL_PREFIX = '{$models}';
        const OFFLINE_HTML = '{$offline}';

        self.addEventListener('install', () => self.skipWaiting());

        self.addEventListener('activate', (event) => {
            event.waitUntil(
                caches.keys()
                    .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
                    .then(() => self.clients.claim())
            );
        });

        self.addEventListener('fetch', (event) => {
            const request = event.request;

            if (request.method !== 'GET') {
                return;
            }

            // Model weights: cache-first, and never re-validated. They only
            // change when MODEL_PREFIX or the cache version does.
            if (request.url.startsWith(MODEL_PREFIX)) {
                event.respondWith(
                    caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE).then((c) => c.put(request, copy));
                        }
                        return response;
                    }))
                );
                return;
            }

            // Everything else: network only, with a plain notice if the
            // network is gone. Nothing about a punch may come from a cache.
            if (request.mode === 'navigate') {
                event.respondWith(
                    fetch(request).catch(() => new Response(OFFLINE_HTML, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                    }))
                );
            }
        });
        JS;

        return response($js)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', $scope);
    }

    /**
     * The app's BASE path, which the worker's scope must match exactly.
     *
     * Derived from the manifest's own URL, not from the punch screen. The
     * punch screen is /staff/clock, and scoping the worker there would leave
     * Punches, Leave and Time Off outside it — uncontrolled, and offline for
     * nobody. The manifest sits at the app root, so its directory IS the base.
     */
    private function scope(): string
    {
        $path = parse_url(route('clock.staff.manifest', absolute: false), PHP_URL_PATH) ?: '/';

        // dirname() returns a backslash on Windows for a root-level path, and
        // a scope has to be a URL path either way.
        $base = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', dirname($path)), '/');

        return $base . '/';
    }
}
