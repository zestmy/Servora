<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('supplier')->check()) {
            return redirect()->route('supplier.login');
        }

        if (! Auth::guard('supplier')->user()->is_active) {
            Auth::guard('supplier')->logout();
            return redirect()->route('supplier.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
