<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LmsGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('lms')->check()) {
            return redirect()->route('lms.dashboard');
        }

        return $next($request);
    }
}
