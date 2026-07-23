<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spatie teams mode: the permission "team" is the user's ACTIVE company
 * (users.company_id). Must run inside the web group after the session starts
 * so role/permission checks are scoped before any route middleware fires.
 */
class SetPermissionsTeamFromCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user() ?? (Auth::guard('lms')->check() ? Auth::guard('lms')->user() : null);

        if ($user) {
            setPermissionsTeamId($user->company_id);
        }

        return $next($request);
    }
}
