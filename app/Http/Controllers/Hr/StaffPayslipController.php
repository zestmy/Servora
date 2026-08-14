<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Scopes\CompanyScope;
use App\Services\Payroll\PayslipPdf;
use App\Services\Staff\StaffSession;

/**
 * An employee's own payslip, from the Staff Portal.
 *
 * Separate from PayslipController rather than a branch inside it, because the
 * two answer different questions. That one asks "may this USER see this run" —
 * a permission on an authenticated account. This one asks "is this line YOURS"
 * — which is not a permission at all, and the moment those two checks share a
 * method is the moment somebody adds an `if` to the wrong one.
 *
 * The document itself is identical: same locked line, same builder. A payslip
 * an employee downloads and one a manager prints must be the same piece of
 * paper, or there is nothing to have a conversation about.
 */
class StaffPayslipController extends Controller
{
    /*
     * The route parameter is read BY NAME. This route is mounted on
     * {companySlug}.servora.com.my, so companySlug is the first parameter and
     * Laravel's dispatcher spreads them positionally — argument #1 would be
     * the slug, not the id, and the request 500s. It cannot reproduce locally,
     * where APP_DOMAIN is unset and there is no slug. See StaffPhotoController.
     */
    public function show(Request $request, StaffSession $session, PayslipPdf $pdf)
    {
        $line     = (int) $request->route('line');
        $employee = $session->employee($session->companyId());

        // The middleware already redirected if there was no session; reaching
        // here without one means it went stale mid-request.
        abort_unless($employee, 403, 'Sign in again.');

        /*
         * Every condition in one query, and every one of them load-bearing:
         *
         *   company_id  — no authenticated user, so CompanyScope is not doing
         *                 this for us.
         *   employee_id — the id in the URL is a guess anybody can make, and
         *                 the thing being guessed at is somebody's salary.
         *   run status  — a draft moves. See the Payslips component.
         *
         * A 404 rather than a 403 for all of them: "this is not yours" and
         * "this does not exist" should be indistinguishable from outside, or
         * the response itself confirms which line ids are real.
         */
        $row = PayrollRunLine::withoutGlobalScope(CompanyScope::class)
            ->where('id', $line)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereHas('run', fn ($q) => $q
                ->withoutGlobalScope(CompanyScope::class)
                ->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID]))
            ->with('run.company')
            ->first();

        abort_unless($row && $row->run, 404);

        // Inline, not an attachment. A phone opens this in its own viewer and
        // the person reads it there; forcing a download leaves a file in a
        // folder they will never find again.
        return $pdf->make($row->run, collect([$row]), $row->run->company)
            ->stream($pdf->filename($row->run, $row->employee_name));
    }
}
