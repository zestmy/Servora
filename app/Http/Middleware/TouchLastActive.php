<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records users.last_active_at on authenticated requests, throttled to one
 * write per 5 minutes. This is the activity source for Company Health, the
 * admin dashboards and user lists — the DB sessions table stopped being a
 * reliable source when production sessions moved to Redis (2026-06-29).
 * Direct DB update so updated_at and model events stay untouched.
 */
class TouchLastActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5)))) {
            DB::table('users')->where('id', $user->id)->update(['last_active_at' => now()]);
        }

        return $next($request);
    }
}
