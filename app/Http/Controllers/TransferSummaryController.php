<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Services\PurchaseSupplierBreakdown;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Transfers in a range, totalled per sending outlet and filed as a PDF.
 *
 * A transfer carries no cost of its own — App\Livewire\Inventory\Index::TABS
 * marks 'amount' => null for this tab — the value lives on its lines, one
 * quantity and unit cost per ingredient moved. So unlike Wastage (grouped by
 * department, straight off the header) or Staff Meals (grouped by outlet,
 * same), this report sums quantity × unit_cost per line first and groups
 * the result by the outlet that sent it — the same computation and grouping
 * the on-screen chart uses (Index::transferOutletChartData()).
 *
 * Filters arrive in the query string because the screen owns them — see
 * ConsolidatedStockTakeController's doc comment for why none of them are
 * trusted as-is.
 */
class TransferSummaryController extends Controller
{
    use ScopesToActiveOutlet;

    /** Transfers listed under each outlet before the block says "+N more". */
    private const DETAIL_ROWS = 15;

    /** Past this many transfers the per-outlet listing is dropped; the totals above stay exact regardless. */
    private const DETAIL_CEILING = 2000;

    public function __invoke(Request $request)
    {
        $data = $this->load($request);

        $pdf = Pdf::loadView('pdf.inventory-group-summary', $data)->setPaper('a4', 'portrait');

        return $pdf->download('Transfer-Summary-' . $data['scope']['from'] . '-to-' . $data['scope']['to'] . '.pdf');
    }

    /** @return array<string, mixed> everything pdf.inventory-group-summary (and the Excel writer) need */
    protected function load(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $transfers = $this->scopedQuery($request, $from, $to, $status, $search)
            ->with(['lines', 'fromOutlet', 'toOutlet'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get()
            ->each(function (OutletTransfer $t) {
                $t->computed_value = (float) $t->lines->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_cost);
            });

        $groups  = $this->byOutlet($transfers);
        $totals  = $this->totals($groups, $from, $to);
        $details = $this->details($transfers, $groups, $totals['count']);

        $company = Company::find(Auth::user()->company_id);

        $scope = ['from' => $from, 'to' => $to];

        $scopeRows = collect([
            ['Outlet', ($id = $this->selectedOutletId($request->query('outlet'))) ? (Outlet::find($id)?->name ?? '—') : 'All outlets'],
            ['Status', $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'All statuses'],
            ['Search', $search !== '' ? '"' . $search . '"' : '—'],
        ]);

        return [
            'company'        => $company,
            'docTitle'       => 'Transfer Summary',
            'scope'          => $scope,
            'scopeRows'      => $scopeRows,
            'groupLabel'     => 'Outlet (sender)',
            'valueLabel'     => 'Value',
            'noun'           => 'transfer',
            'groups'         => $groups,
            'totals'         => $totals,
            'detailBlocks'   => $details['blocks'],
            'omittedDetails' => $details['omitted'],
            'detailColumns'  => $this->detailColumns(),
            'companyName'    => $company?->name,
            'generatedBy'    => Auth::user()->name,
        ];
    }

    /** @return array<int, array{label:string,align?:string,width?:int,type?:string,value:\Closure}> */
    private function detailColumns(): array
    {
        return [
            ['label' => 'Date', 'width' => 82, 'value' => fn (OutletTransfer $t) => $t->transfer_date?->format('d M Y') ?? '—'],
            ['label' => 'Transfer #', 'width' => 108, 'value' => fn (OutletTransfer $t) => $t->transfer_number ?: '—'],
            ['label' => 'To', 'value' => fn (OutletTransfer $t) => $t->toOutlet?->name ?? '—'],
            ['label' => 'Status', 'width' => 70, 'value' => fn (OutletTransfer $t) => ucfirst(str_replace('_', ' ', $t->status))],
            ['label' => 'Value (RM)', 'align' => 'right', 'width' => 78, 'type' => 'number', 'value' => fn (OutletTransfer $t) => $t->computed_value],
        ];
    }

    /** Mirrors App\Livewire\Inventory\Index::filtered() for the transfers tab: a transfer belongs to both the outlet that sent it and the one receiving. */
    private function scopedQuery(Request $request, string $from, string $to, string $status, string $search)
    {
        $query = OutletTransfer::query()->whereBetween('transfer_date', [$from, $to]);

        $ids = $this->selectedOutletId($request->query('outlet'))
            ? [$this->selectedOutletId($request->query('outlet'))]
            : $this->availableOutletIds();

        if (! empty($ids)) {
            $query->where(fn ($q) => $q->whereIn('from_outlet_id', $ids)->orWhereIn('to_outlet_id', $ids));
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('transfer_number', 'like', '%' . $search . '%');
        }

        return $query;
    }

    /**
     * @param  Collection<int, OutletTransfer>  $transfers
     * @return array<int, array{rank:int,name:string,anchor:string,color:string,count:int,value:float,share:float,group_key:int}>
     */
    private function byOutlet(Collection $transfers): array
    {
        $rows = $transfers
            ->groupBy('from_outlet_id')
            ->map(fn (Collection $group, $outletId) => [
                'outlet_id' => (int) $outletId,
                'name'      => $group->first()->fromOutlet?->name ?? '—',
                'count'     => $group->count(),
                'value'     => (float) $group->sum('computed_value'),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        $total = (float) $rows->sum('value');

        return $rows->map(fn (array $row, int $i) => [
            'rank'      => $i + 1,
            'name'      => $row['name'],
            'anchor'    => 'group-' . ($i + 1),
            'color'     => PurchaseSupplierBreakdown::SERIES[$i] ?? PurchaseSupplierBreakdown::OTHER_COLOR,
            'count'     => $row['count'],
            'value'     => $row['value'],
            'share'     => $total > 0 ? ($row['value'] / $total) * 100 : 0.0,
            'group_key' => $row['outlet_id'],
        ])->values()->all();
    }

    /** @param array<int, array<string, mixed>> $groups */
    private function totals(array $groups, string $from, string $to): array
    {
        $value = array_sum(array_column($groups, 'value'));
        $count = array_sum(array_column($groups, 'count'));
        $days  = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $top   = $groups[0] ?? null;

        return [
            'value'    => $value,
            'count'    => $count,
            'groups'   => count($groups),
            'average'  => $count > 0 ? $value / $count : 0.0,
            'perDay'   => $days > 0 ? $value / $days : 0.0,
            'days'     => $days,
            'topName'  => $top['name'] ?? '—',
            'topValue' => $top['value'] ?? 0.0,
            'topShare' => $top['share'] ?? 0.0,
        ];
    }

    /**
     * @param  Collection<int, OutletTransfer>  $transfers
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{omitted: bool, blocks: array<int, array<string, mixed>>}
     */
    private function details(Collection $transfers, array $groups, int $count): array
    {
        if ($count === 0 || $count > self::DETAIL_CEILING) {
            return ['omitted' => $count > 0, 'blocks' => []];
        }

        $grouped = $transfers->groupBy('from_outlet_id');

        $blocks = [];

        foreach ($groups as $g) {
            $own = $grouped->get($g['group_key'], collect())->values();

            $blocks[] = [
                'group' => $g,
                'rows'  => $own->take(self::DETAIL_ROWS),
                'more'  => max(0, $own->count() - self::DETAIL_ROWS),
            ];
        }

        return ['omitted' => false, 'blocks' => $blocks];
    }

    /** A date we can use, or nothing — never an exception from a hand-typed URL. */
    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
