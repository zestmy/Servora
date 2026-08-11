<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The medical certificate behind a leave request.
 *
 * TWO READERS, TWO DIFFERENT QUESTIONS, and they are deliberately separate
 * methods rather than one with a branch — the same reasoning that keeps
 * StaffPayslipController apart from PayslipController. The manager's route
 * asks "may this USER see this employee", which is a permission plus outlet
 * scope. The staff route asks "is this request YOURS", which is not a
 * permission at all. The moment those share a method is the moment somebody
 * adds an `if` to the wrong one.
 *
 * Served from the private disk through here rather than linked, because an MC
 * carries a name, a clinic, a date and often a diagnosis.
 */
class LeaveAttachmentController extends Controller
{
    /** A manager or approver looking at the request in front of them. */
    public function show(LeaveRequest $leaveRequest): StreamedResponse|Response
    {
        abort_unless(Auth::user()?->can('hr.leave'), 403);

        $employee = $leaveRequest->employee;

        abort_if(! $employee, 404);

        // (int) before the strict comparison: PDO hands foreign keys back as
        // strings, so in_array("1", [1, 2], true) is false and every document
        // 403s into a blank box.
        abort_unless(
            in_array((int) $employee->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403,
            'That employee is not at one of your outlets.'
        );

        return $this->stream($leaveRequest);
    }

    /** The employee's own, from the staff portal's PIN session. */
    public function mine(int $leaveRequest, StaffSession $session): StreamedResponse|Response
    {
        $employee = $session->employee($session->companyId());

        abort_unless($employee, 403, 'Sign in again.');

        /*
         * Both conditions load-bearing. There is no authenticated user, so
         * CompanyScope is not doing this for us, and the id in the URL is a
         * guess anybody can make at somebody else's medical certificate.
         *
         * 404 rather than 403: "this is not yours" and "this does not exist"
         * should be indistinguishable from outside.
         */
        $request = LeaveRequest::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->find($leaveRequest);

        abort_if(! $request, 404);

        return $this->stream($request);
    }

    private function stream(LeaveRequest $request): StreamedResponse|Response
    {
        $path = $request->attachment_path;

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        // Inline, because the point is to look at it: an approver deciding on
        // a request should not have to open a downloads folder to read the
        // date on a clinic slip.
        return Storage::disk('local')->response(
            $path,
            $request->attachment_name ?: 'medical-certificate',
            ['Content-Disposition' => 'inline; filename="'
                . str_replace(['"', '/', '\\'], '-', $request->attachment_name ?: 'medical-certificate') . '"'],
        );
    }
}
