<?php

namespace App\Http\Controllers\Audits;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use App\Services\Pdf\PdfImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The audit report: one page of facts and scores, then only what went wrong.
 *
 * Passed and N/A lines are NOT printed. A ROSE form is three hundred items,
 * and a report that lists all of them runs to nine pages of "OK" in which the
 * three findings anyone came for are impossible to find. The full checklist
 * is on screen; the paper is for the score, the sign-off, and the fixes.
 *
 * RENDERED SYNCHRONOUSLY, unlike the SOP handbook. That export is two hundred
 * recipes with full-size plating photos (~500 MB peak); this is one audit with
 * at most a few dozen finding photos, each shrunk to a 160px thumbnail by
 * PdfImage before dompdf sees it, and measured comfortably inside the
 * 256M / 60s php-fpm limits. If a company's audits grow past that, this is
 * the file to move onto the queued-export pattern — nothing in the views
 * would change.
 */
class AuditReportController extends Controller
{
    public function __invoke(int $id, PdfImage $images)
    {
        $audit = Audit::with(['outlet', 'auditor', 'sections'])->findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($audit->outlet_id), 403);
        abort_if($audit->isDraft(), 404, 'The report is available once the audit is submitted.');

        $company = Company::find($audit->company_id);

        $findings = AuditFinding::with(['photos', 'actions.owner', 'actions.verifiedBy', 'line'])
            ->where('audit_id', $audit->id)
            ->get()
            ->sortBy(fn ($f) => $f->line?->sort_order ?? 0)
            ->groupBy('section_name');

        $thumbs = [];
        foreach ($findings->flatten(1) as $finding) {
            foreach ($finding->photos as $photo) {
                if ($uri = $images->thumb($photo->file_path)) {
                    $thumbs[$photo->id] = $uri;
                }
            }
        }

        $signature = null;
        if ($audit->signature_path && Storage::disk('local')->exists($audit->signature_path)) {
            $signature = 'data:image/png;base64,' . base64_encode(Storage::disk('local')->get($audit->signature_path));
        }

        $pdf = Pdf::loadView('pdf.audit-report', [
            'audit'     => $audit,
            'company'   => $company,
            'findings'  => $findings,
            'thumbs'    => $thumbs,
            'signature' => $signature,
            'logo'      => $images->logo($company?->logo),
        ])->setPaper('a4', 'portrait');

        $name = trim(($audit->template_code ?: 'Audit') . '-' . ($audit->outlet?->code ?: $audit->outlet?->name) . '-' . $audit->audit_date->format('Ymd'));

        return $pdf->stream(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) . '.pdf');
    }
}
