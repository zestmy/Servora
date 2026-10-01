<?php

namespace App\Http\Controllers;

use App\Models\AiInvoiceScan;
use App\Models\AuditFindingPhoto;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionPhoto;
use App\Models\ProcurementInvoice;
use App\Models\SalesRecordAttachment;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Streams the files that used to sit on the public disk, after checking who
 * is asking.
 *
 * Every lookup goes through a company-scoped parent (the photo and attachment
 * models carry no scope of their own) and then the outlet, so the id in the
 * URL is never enough on its own. Not-yours and does-not-exist both answer
 * 404, so the URLs cannot be used to probe what exists.
 *
 * The route's `can:` has already checked the ability; it is repeated here so
 * the controller is safe wherever it is mounted.
 */
class PrivateFileController extends Controller
{
    public function salesAttachment(int $attachment)
    {
        abort_unless(Auth::user()?->can('sales.view'), 403);

        $file   = SalesRecordAttachment::find($attachment);
        $record = $file?->salesRecord;   // company-scoped: another company's is null

        abort_unless($file && $record && Auth::user()->canAccessOutlet((int) $record->outlet_id), 404);

        return PrivateFiles::response($file->file_path, $file->file_name);
    }

    /** The upload behind an AI invoice scan, before or after it became an invoice. */
    public function invoiceScan(int $scan)
    {
        abort_unless(Auth::user()?->can('purchasing.invoice'), 403);

        $scan = AiInvoiceScan::find($scan);   // company-scoped
        abort_unless($scan, 404);

        return PrivateFiles::response($scan->original_file_path, $scan->original_file_name);
    }

    public function procurementInvoice(int $invoice)
    {
        abort_unless(Auth::user()?->can('purchasing.invoice'), 403);

        $invoice = ProcurementInvoice::find($invoice);   // company-scoped
        abort_unless($invoice && $invoice->original_file_path, 404);
        abort_if($invoice->outlet_id && ! Auth::user()->canAccessOutlet((int) $invoice->outlet_id), 404);

        return PrivateFiles::response($invoice->original_file_path);
    }

    public function auditPhoto(int $photo)
    {
        abort_unless(Auth::user()?->can('audits.view'), 403);

        $photo = AuditFindingPhoto::find($photo);
        $audit = $photo?->finding?->audit;   // finding and audit are company-scoped

        abort_unless($photo && $audit && Auth::user()->canAccessOutlet((int) $audit->outlet_id), 404);

        return PrivateFiles::response($photo->file_path);
    }

    public function actionPhoto(int $photo)
    {
        abort_unless(Auth::user()?->can('audits.view'), 403);

        $photo  = CorrectiveActionPhoto::find($photo);
        $action = $photo?->action;   // company-scoped

        abort_unless($photo && $action && Auth::user()->canAccessOutlet((int) $action->outlet_id), 404);

        return PrivateFiles::response($photo->file_path);
    }

    /**
     * The same photo from the staff app's PIN session.
     *
     * No authenticated user, so CompanyScope is not doing anything here: the
     * company and the employee are checked by hand. A staff member sees the
     * photos of actions they own or that sit at their outlet — the same two
     * lists Staff\CorrectiveActions shows them.
     *
     * The route parameter is read BY NAME: these routes are mounted on
     * {companySlug}.servora.com.my, so a positional argument would be the slug.
     */
    public function staffActionPhoto(Request $request, StaffSession $session)
    {
        $employee = $session->employee($session->companyId());
        abort_unless($employee, 403, 'Sign in again.');

        $photo = CorrectiveActionPhoto::find((int) $request->route('photo'));
        $action = $photo
            ? CorrectiveAction::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $employee->company_id)
                ->where(fn ($q) => $q->where('owner_employee_id', $employee->id)
                    ->orWhere('outlet_id', $employee->outlet_id))
                ->find($photo->corrective_action_id)
            : null;

        abort_unless($photo && $action, 404);

        return PrivateFiles::response($photo->file_path);
    }
}
