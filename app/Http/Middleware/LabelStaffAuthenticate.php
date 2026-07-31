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
 */
class LabelStaffAuthenticate
{
    public function __construct(private LabelStaffSession $staff)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->staff->check($this->staff->companyId())) {
            return redirect()->route('labels.staff.login');
        }

        return $next($request);
    }
}
