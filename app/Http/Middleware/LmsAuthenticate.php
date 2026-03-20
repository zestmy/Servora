<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LmsAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('lms')->check()) {
            // Find company slug from last logged-in user or first active company
            $slug = session('lms_company_slug')
                ?? Company::where('is_active', true)->value('slug')
                ?? 'app';

            return redirect()->route('lms.login', ['companySlug' => $slug])
                ->with('intended', $request->fullUrl());
        }

        return $next($request);
    }
}
