<?php

namespace App\Http\Middleware;

use App\Services\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a route whose module the company does not have
 * (config/modules.php `routes`, App\Services\Entitlements).
 *
 * One middleware keyed on the route NAME rather than a `module:x` parameter
 * on each of several hundred routes: the map lives in one file, and a route
 * group cannot forget it. It sits at the end of every group that carries a
 * company — the main app, the staff portal, the label PWA, the training
 * portal, both agent APIs — so it runs after whatever resolves that company.
 *
 * Also Livewire persistent middleware, so /livewire/update is judged against
 * the page the component was rendered on.
 */
class EnforceModuleAccess
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $module = $this->entitlements->moduleForRoute($request->route()?->getName());

        if ($module === null || $this->entitlements->isSwitch($module)) {
            // Switches have their own 404 (EnsureSupplierPortalEnabled).
            return $next($request);
        }

        // The platform's own staff are not a tenant and are never on a plan.
        if (Auth::guard('web')->user()?->isSystemRole()) {
            return $next($request);
        }

        $company = $this->entitlements->companyForRequest($request);

        if ($this->entitlements->allows($company, $module)) {
            return $next($request);
        }

        $message = $this->entitlements->name($module).' is not on your plan.';

        // Agents, the kiosk and other machines: a status they can act on.
        if ($request->expectsJson() || ! $request->isMethod('GET') && ! $request->hasHeader('X-Livewire')) {
            return response()->json(['message' => $message, 'module' => $module], 403);
        }

        // A manager on a page: take them to where it can be switched on. Billing
        // is users.manage-only, so anyone else would land on a 403 there — send
        // them to the dashboard with a message they can act on instead.
        if (! $request->hasHeader('X-Livewire') && Auth::guard('web')->check()) {
            if (Auth::guard('web')->user()->canDo('users.manage')) {
                return redirect()->route('billing.index')
                    ->with('error', $message.' Upgrade to use it.');
            }

            return redirect()->route('dashboard')
                ->with('error', $message.' Ask your manager.');
        }

        // Staff apps and Livewire calls: nothing here they can buy.
        abort(403, $message.' Ask your manager.');
    }
}
