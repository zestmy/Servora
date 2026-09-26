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

        $findings = AuditFinding::with(['photos', 'actions.owner', 'actions.verifiedBy', 'actions.photos', 'line'])
            ->where('audit_id', $audit->id)
            ->get()
            ->sortBy(fn ($f) => $f->line?->sort_order ?? 0)
            ->groupBy('section_name');

        $thumbs = [];
        $actionThumbs = [];
        foreach ($findings->flatten(1) as $finding) {
            foreach ($finding->photos as $photo) {
                if ($uri = $images->thumb($photo->file_path)) {
                    $thumbs[$photo->id] = $uri;
                }
            }
            foreach ($finding->actions as $action) {
                foreach ($action->photos as $photo) {
                    if ($uri = $images->thumb($photo->file_path)) {
                        $actionThumbs[$photo->id] = $uri;
                    }
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
            'actionThumbs' => $actionThumbs,
            'signature' => $signature,
            'logo'      => $images->logo($company?->logo),
            'history'   => self::history($audit),
        ])->setPaper('a4', 'portrait');

        $name = trim(($audit->template_code ?: 'Audit') . '-' . ($audit->outlet?->code ?: $audit->outlet?->name) . '-' . $audit->audit_date->format('Ymd'));

        return $pdf->stream(preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) . '.pdf');
    }

    /** How far back the score history on the report looks. */
    public const HISTORY_MONTHS = 12;

    /** Rows on the history block, this audit included. */
    public const HISTORY_ROWS = 8;

    /**
     * This outlet's recent audits of the same form, oldest first, this one
     * last — each with its score, outcome and the change against the one
     * before it. Same form only: a ROSE score and a pre-opening score are
     * not on the same scale. Drafts are excluded; so are audits dated after
     * this one, because a report is about the day it was written.
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, date:\Carbon\Carbon, score:float, outcome:?string, label:?string, delta:?float, current:bool}>
     */
    public static function history(Audit $audit): \Illuminate\Support\Collection
    {
        if (! $audit->audit_template_id) {
            return collect();
        }

        $rows = Audit::withoutGlobalScopes()
            ->where('company_id', $audit->company_id)
            ->where('outlet_id', $audit->outlet_id)
            ->where('audit_template_id', $audit->audit_template_id)
            ->where('status', '!=', Audit::STATUS_DRAFT)
            ->whereNotNull('score_percent')
            ->whereNull('deleted_at')
            ->whereDate('audit_date', '>=', $audit->audit_date->copy()->subMonths(self::HISTORY_MONTHS)->toDateString())
            ->where(fn ($q) => $q->whereDate('audit_date', '<', $audit->audit_date->toDateString())
                ->orWhere(fn ($w) => $w->whereDate('audit_date', $audit->audit_date->toDateString())->where('id', '<=', $audit->id)))
            ->orderBy('audit_date')->orderBy('id')
            ->get(['id', 'audit_date', 'score_percent', 'outcome'])
            ->take(-self::HISTORY_ROWS)
            ->values();

        $previous = null;

        return $rows->map(function (Audit $row) use (&$previous, $audit) {
            $score = (float) $row->score_percent;
            $entry = [
                'id'      => $row->id,
                'date'    => $row->audit_date,
                'score'   => $score,
                'outcome' => $row->outcome,
                'label'   => $row->outcomeLabel(),
                'delta'   => $previous === null ? null : round($score - $previous, 1),
                'current' => $row->id === $audit->id,
            ];
            $previous = $score;

            return $entry;
        });
    }
}
