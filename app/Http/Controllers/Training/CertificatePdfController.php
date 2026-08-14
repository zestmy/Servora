<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCertificate;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The printable certificate.
 *
 * Rendered on demand from the row rather than stored as a file — see
 * TrainingCertificate — so it always carries the company's current branding and
 * there is nothing to back up.
 *
 * A single certificate is one page of text and a logo, so it renders inside the
 * ordinary request limits. Nothing here needs the memory ceiling the SOP
 * exports raise.
 */
class CertificatePdfController extends Controller
{
    /**
     * Download a certificate.
     *
     * Two callers, and neither is an ordinary route gate. A signed-in manager
     * on the `web` guard may take any certificate in THEIR company; the person
     * it belongs to arrives from the staff app, which is a PIN session rather
     * than a guard — so `auth` middleware could not express either half.
     *
     * The company scope is dropped and re-checked by hand because it would
     * otherwise be satisfied by whichever guard happened to be signed in,
     * which is not the same question as "is this yours".
     */
    /*
     * THE ROUTE PARAMETER IS READ BY NAME, not taken as an argument.
     *
     * These routes are mounted on {companySlug}.servora.com.my, so the route
     * has TWO parameters and companySlug comes first — and Laravel's
     * ControllerDispatcher spreads them POSITIONALLY
     * (`...array_values($parameters)`). Argument #1 is therefore the SLUG,
     * not the id: "must be of type int, string given", a 500, every time.
     *
     * It survived review because it cannot happen locally. APP_DOMAIN is unset
     * in development and in the test suite, so the same routes mount on a path
     * with no companySlug, the single parameter lands in the right position,
     * and everything passes. Reading by name is correct in both.
     */
    public function show(Request $request, StaffSession $staff)
    {
        $id = (int) $request->route('id');

        $certificate = TrainingCertificate::withoutGlobalScope(CompanyScope::class)
            ->with(['employee:id,name,company_id', 'course:id,title', 'company'])
            ->findOrFail($id);

        $webUser  = Auth::user();
        $employee = $staff->employee();

        $allowed = ($webUser
                && $webUser->company_id === $certificate->company_id
                && $webUser->can('training.view'))
            || ($employee && $employee->id === $certificate->employee_id);

        abort_unless($allowed, 403);

        // A revoked certificate must not keep printing as if it were valid.
        abort_if($certificate->isRevoked(), 410, 'This certificate has been revoked.');

        $pdf = Pdf::loadView('pdf.training-certificate', [
            'certificate' => $certificate,
            'company'     => $certificate->company,
        ])->setPaper('a4', 'landscape');

        $safeName = preg_replace('/[^A-Za-z0-9]+/', '-', $certificate->recipient_name) ?: 'certificate';

        return $pdf->download(strtolower(trim($safeName, '-')) . '-' . $certificate->serial . '.pdf');
    }
}
