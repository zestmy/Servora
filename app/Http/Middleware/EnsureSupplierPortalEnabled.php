<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404s every supplier-portal and marketplace route while the module is parked
 * (config/modules.php). The routes stay registered so route() calls in the
 * parked views keep resolving; only reaching them is refused.
 *
 * Registered as Livewire persistent middleware too, so a tab left open on a
 * portal page cannot keep writing through /livewire/update.
 */
class EnsureSupplierPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('modules.supplier_portal'), 404);

        return $next($request);
    }
}
