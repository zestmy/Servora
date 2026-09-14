<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LmsGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('lms')->user();

        // Only bounce to the dashboard when signed in to THIS company's portal.
        // The same email can hold accounts in several companies, so a session
        // from another company must not block this portal's login/register.
        if ($user && $this->portalCompanyId($request) === (int) $user->company_id) {
            return redirect()->route('lms.dashboard');
        }

        return $next($request);
    }

    private function portalCompanyId(Request $request): ?int
    {
        if (app()->bound('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        $slug = $request->route('companySlug');

        return $slug ? Company::where('slug', $slug)->value('id') : null;
    }
}
