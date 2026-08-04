<?php

namespace App\Http\Middleware;

use App\Services\Staff\StaffSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the staff clock-in app behind the staff PIN.
 *
 * Runs after company.subdomain, so the resolved company is available and the
 * session is checked against it: a session opened on one company's subdomain
 * must not authorise a punch on another's.
 *
 * A separate class from LabelStaffAuthenticate despite sharing a session,
 * because it bounces to a different login and remembers a different intended
 * URL. Sending somebody who opened /clock to the labels sign-in, and then on
 * to the label printer, would be its own small betrayal at 8:59am.
 */
class ClockStaffAuthenticate
{
    public const INTENDED_KEY = 'clock_staff_intended';

    public function __construct(private StaffSession $staff)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->staff->check($this->staff->companyId())) {
            if ($request->isMethod('GET') && ! $request->ajax()) {
                $request->session()->put(self::INTENDED_KEY, $request->fullUrl());
            }

            return redirect()->route('clock.staff.login');
        }

        return $next($request);
    }
}
