<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Display-only timezone middleware.
 *
 * Adjusts the PHP / app timezone to the authenticated user's (web guard)
 * or company's (lms guard) preference for read-only requests (GET / HEAD).
 * Write requests (POST/PUT/PATCH/DELETE) keep the default app timezone
 * (Asia/Kuala_Lumpur) so records stay anchored to the business day.
 */
class SetDisplayTimezone
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $tz = null;

        if (Auth::check()) {
            $user = Auth::user();
            $tz = $user->timezone ?: $user->company?->timezone;
        } elseif (Auth::guard('lms')->check()) {
            $tz = Auth::guard('lms')->user()->company?->timezone;
        }

        if ($tz && $this->isValid($tz)) {
            date_default_timezone_set($tz);
            config(['app.timezone' => $tz]);
        }

        return $next($request);
    }

    private function isValid(string $tz): bool
    {
        return in_array($tz, \DateTimeZone::listIdentifiers(), true);
    }
}
