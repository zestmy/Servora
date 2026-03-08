<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->company_id) {
            // System Admin may not have a company — allow through to settings/users only
            if ($user->hasRole('System Admin')) {
                return $next($request);
            }

            return redirect()->route('login')->with('error', 'Your account is not assigned to a company. Please contact your administrator.');
        }

        return $next($request);
    }
}
