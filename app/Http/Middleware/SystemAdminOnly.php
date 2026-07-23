<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces Spatie's RoleMiddleware for system areas: system roles must apply
 * regardless of the active company, and in teams mode hasRole() only sees the
 * current team's assignment rows. isSystemRole() checks team-agnostically.
 */
class SystemAdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSystemRole(), 403);

        return $next($request);
    }
}
