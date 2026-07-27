<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Blocks suspended accounts and members of suspended companies on every
 * authenticated web request (suspension takes effect even mid-session).
 *
 * Exemptions: system-role users are never blocked by company suspension
 * (they administer the platform), and an admin impersonating a suspended
 * user keeps working — the impersonator_id session key marks support
 * access and the banner offers the way back.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $request->session()->has('impersonator_id')) {
            if ($user->suspended_at) {
                return $this->deny($request, 'Your account has been suspended. Please contact support.');
            }

            if ($user->company_id && ! $user->isSystemRole()) {
                $companyActive = Company::where('id', $user->company_id)->value('is_active');
                if ($companyActive !== null && ! $companyActive) {
                    return $this->deny($request, 'Your company account has been suspended. Please contact support.');
                }
            }
        }

        return $next($request);
    }

    protected function deny(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
