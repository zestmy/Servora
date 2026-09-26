<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Lms\StaffHandoffController;
use App\Models\Company;
use App\Services\Staff\StaffSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LmsAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        /*
         * Access opened from the Staff Portal is only as good as the staff
         * session behind it. That session goes stale when the PIN changes or
         * the employee is deactivated — without this check the LMS login it
         * produced would outlive both, indefinitely.
         */
        $viaStaff = $request->session()->get(StaffHandoffController::SESSION_KEY);

        if ($viaStaff && Auth::guard('lms')->check()) {
            $staff    = app(StaffSession::class);
            $employee = $staff->employee($staff->companyId());

            if (! $employee || $employee->id !== (int) $viaStaff) {
                // Resolved before logout, which takes the company with it.
                $slug = Auth::guard('lms')->user()?->company?->slug;

                Auth::guard('lms')->logout();
                $request->session()->forget(StaffHandoffController::SESSION_KEY);
                $request->session()->put(ClockStaffAuthenticate::INTENDED_KEY, StaffHandoffController::staffUrl('clock.staff.lms', $slug));

                return redirect()->to(StaffHandoffController::staffUrl('clock.staff.login', $slug));
            }
        }

        if (! Auth::guard('lms')->check()) {
            // If on a company subdomain, redirect to /lms/login on the same subdomain
            if (app()->bound('currentCompany')) {
                $company = app('currentCompany');
                return redirect()->to('/lms/login')
                    ->with('intended', $request->fullUrl());
            }

            // Fall back to slug-based route
            $slug = session('lms_company_slug')
                ?? Company::where('is_active', true)->value('slug')
                ?? 'app';

            return redirect()->route('lms.login', ['companySlug' => $slug])
                ->with('intended', $request->fullUrl());
        }

        return $next($request);
    }
}
