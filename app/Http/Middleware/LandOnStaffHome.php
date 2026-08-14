<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An app LAUNCH at the clock screen goes to Home instead.
 *
 * Reported three times as "the Staff Portal still lands on Clock-In", and each
 * previous fix was correct about a different route in. The `/staff` route,
 * the post-sign-in fallback and the manifest's start_url all point at Home;
 * a stale intended URL is now ignored. The access log shows what is left:
 *
 *     GET /staff/clock  200  referer "-"
 *
 * No sign-in, no redirect — an app installed before the landing page moved
 * simply launches the old start_url, and iOS NEVER REFETCHES the manifest of an
 * installed web app. Remove-and-re-add is the only client-side cure, and asking
 * a floor to do that is not a fix.
 *
 * SO THE LAUNCH IS DETECTED AND SENT HOME. `Sec-Fetch-Site: none` means the
 * navigation had no originating page: a launcher icon, a typed address or a
 * bookmark. An in-app tap on the Clock tab is a same-origin link and sends
 * `same-origin`; a wire:navigate visit is a fetch and is not a navigation at
 * all. So the tab keeps working and only the doorway moves.
 *
 * IT FAILS SAFE. If the header is absent — an older browser, or one that
 * strips it — nothing is redirected and the behaviour is exactly what it is
 * today. A wrong guess here would send somebody who deliberately tapped Clock
 * to the wrong screen, which is worse than the bug being fixed.
 *
 * This can be deleted once the installed apps have all relaunched against the
 * current manifest.
 */
class LandOnStaffHome
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isColdLaunch($request)) {
            return redirect()->route('clock.staff.home');
        }

        return $next($request);
    }

    private function isColdLaunch(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->hasHeader('X-Livewire')) {
            return false;
        }

        // A document navigation with no originating page. Both halves are
        // required: `none` alone would also match a subresource, and Dest
        // alone says nothing about where the navigation came from.
        return $request->header('Sec-Fetch-Site') === 'none'
            && $request->header('Sec-Fetch-Dest', 'document') === 'document'
            && ! $request->headers->has('referer');
    }
}
