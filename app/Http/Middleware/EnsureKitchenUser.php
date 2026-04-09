<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureKitchenUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // System admins always have access
        if ($user->isSystemRole()) {
            return $next($request);
        }

        if (! $user->isKitchenUser()) {
            abort(403, 'You do not have access to the kitchen workspace.');
        }

        return $next($request);
    }
}
