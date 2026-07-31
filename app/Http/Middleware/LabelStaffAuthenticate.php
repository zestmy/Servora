<?php

namespace App\Http\Middleware;

use App\Services\Labels\LabelStaffSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the staff label app behind a PIN.
 *
 * Runs after company.subdomain, so the resolved company is available and
 * the session is checked against it: a session opened on one company's
 * subdomain must not authorise anything on another's.
 *
 * Remembers where the person was heading. Scanning a set's QR code while
 * signed out has to land on that set after the PIN, not on the default
 * screen — otherwise the whole point of the QR is lost and the chef has to
 * find the set by hand anyway.
 */
class LabelStaffAuthenticate
{
    public const INTENDED_KEY = 'labels_staff_intended';

    public function __construct(private LabelStaffSession $staff)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->staff->check($this->staff->companyId())) {
            // Only GETs are worth returning to; a bounced POST replayed after
            // sign-in would be a surprise.
            if ($request->isMethod('GET') && ! $request->ajax()) {
                $request->session()->put(self::INTENDED_KEY, $request->fullUrl());
            }

            return redirect()->route('labels.staff.login');
        }

        return $next($request);
    }
}
