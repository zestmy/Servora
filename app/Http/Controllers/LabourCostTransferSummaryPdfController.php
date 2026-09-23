<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Services\Hr\LabourCostTransferCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Labour cost transfers for a period, summarised by outlet, as one PDF.
 *
 * CONFIRMED transfers only, dated in the period — the same set the list
 * screen's "Summary by outlet" counts (both read LabourCostTransfer::forPeriod).
 * Drafts in the period are left out and counted in a note, so a short total
 * explains itself.
 *
 * Page one: net by outlet. Then one section per outlet: the staff it lent
 * (cost handed off) and borrowed (cost taken on), line by line. With an
 * outlet filter, only that outlet gets a section; the summary table still
 * lists its counterparties so the net is readable.
 */
class LabourCostTransferSummaryPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->endOfMonth()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $accessible = $user->accessibleOutletIds();
        $outletId   = (int) $request->query('outlet', 0);
        // An outlet in the URL is not permission to read that branch.
        $ids = $outletId ? array_values(array_intersect([$outletId], $accessible)) : $accessible;
        abort_if($outletId && ! $ids, 403);

        $transfers = LabourCostTransfer::query()->forPeriod($from, $to, $ids)
            ->where('status', 'confirmed')
            ->with(['lines' => fn ($q) => $q->orderBy('date_start'), 'lines.fromOutlet', 'lines.employee', 'toOutlet'])
            ->orderBy('transfer_date')
            ->orderBy('id')
            ->get();

        $draftCount = LabourCostTransfer::query()->forPeriod($from, $to, $ids)->where('status', 'draft')->count();

        $summary     = LabourCostTransferCalculator::summaryByOutlet(LabourCostTransferCalculator::rowsFromTransfers($transfers));
        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_column($summary, 'outlet_id'))->pluck('name', 'id');

        // Per-outlet detail: what each lent and borrowed.
        $sections = [];
        foreach ($transfers as $t) {
            foreach ($t->lines as $l) {
                $row = [
                    'transfer' => $t->transfer_number,
                    // The event name says more than the category when there is one.
                    'purpose'  => $t->reference ?: $t->purposeLabel(),
                    'employee' => $l->employee_name,
                    'dates'    => $this->range($l->date_start, $l->date_end),
                    'basis'    => $l->quantityLabel(),
                    'salary'   => (float) $l->salary_amount,
                    'ot_hours' => (float) $l->ot_hours,
                    'ot'       => (float) $l->ot_amount,
                    'total'    => (float) $l->total_amount,
                ];
                $sections[$l->from_outlet_id]['lent'][]     = $row + ['other' => $t->toOutlet?->name ?? '—'];
                $sections[$t->to_outlet_id]['borrowed'][]   = $row + ['other' => $l->fromOutlet?->name ?? '—'];
            }
        }

        $focus = $outletId ? $ids : array_keys($sections);
        $sections = collect($sections)
            ->only($focus)
            ->map(fn ($s, $id) => [
                'outlet_id' => $id,
                'name'      => $outletNames[$id] ?? Outlet::withoutGlobalScopes()->find($id)?->name ?? '—',
                'lent'      => $s['lent'] ?? [],
                'borrowed'  => $s['borrowed'] ?? [],
                'lent_total'     => round(collect($s['lent'] ?? [])->sum('total'), 2),
                'borrowed_total' => round(collect($s['borrowed'] ?? [])->sum('total'), 2),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $company = Company::find($user->company_id);
        $scope   = [
            'from'   => $from,
            'to'     => $to,
            'outlet' => $outletId ? (Outlet::find($outletId)?->name ?? '—') : 'All outlets',
        ];

        return Pdf::loadView('pdf.labour-cost-transfer-summary', compact(
            'company', 'scope', 'transfers', 'summary', 'outletNames', 'sections', 'draftCount'
        ))
            ->setPaper('a4', 'portrait')
            ->download('Labour-Cost-Transfer-Summary-' . $from . '-to-' . $to . '.pdf');
    }

    private function range($s, $e): string
    {
        if ($s->isSameDay($e)) return $s->format('j M Y');
        if ($s->isSameMonth($e)) return $s->format('j') . '–' . $e->format('j M Y');
        if ($s->isSameYear($e)) return $s->format('j M') . ' – ' . $e->format('j M Y');

        return $s->format('j M Y') . ' – ' . $e->format('j M Y');
    }

    /** A date we can use, or nothing — never an exception from a hand-typed URL. */
    private function date(?string $value): ?string
    {
        if (! $value) return null;

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
