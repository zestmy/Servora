<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LmsAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('lms')->check()) {
            // Try to find company slug from the LMS user's session or default
            return redirect()->route('lms.login', ['companySlug' => 'app']);
        }

        return $next($request);
    }
}
