<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCertificate;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Barryvdh\DomPDF\Facade\Pdf;
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
    public function show(int $id, StaffSession $staff)
    {
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
