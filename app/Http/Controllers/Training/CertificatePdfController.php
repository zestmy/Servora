<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCertificate;
use App\Scopes\CompanyScope;
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
     * Two guards, because two different people arrive here. A signed-in
     * manager may take any certificate in THEIR company; a trainee may take
     * only their own. The global scope handles the first when a web user is
     * signed in, so the lookup drops it and re-checks by hand — otherwise a
     * trainee on the `lms` guard, whose company_id happens to match, would
     * satisfy the scope and be able to fetch a colleague's.
     */
    public function show(int $id)
    {
        $certificate = TrainingCertificate::withoutGlobalScope(CompanyScope::class)
            ->with(['trainee:id,name,company_id', 'course:id,title', 'company'])
            ->findOrFail($id);

        $webUser = Auth::user();
        $trainee = Auth::guard('lms')->user();

        $allowed = ($webUser
                && $webUser->company_id === $certificate->company_id
                && $webUser->can('training.view'))
            || ($trainee && $trainee->id === $certificate->lms_user_id);

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
