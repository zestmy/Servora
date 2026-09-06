<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\StaffMealRecord;
use App\Services\PurchaseSupplierBreakdown;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Staff meals in a range, totalled per outlet and filed as a PDF.
 *
 * Staff meals are tagged to the outlet, not a department (the record form
 * doesn't even ask for one — see StaffMealForm) — so where the Wastage
 * summary groups by department, this one groups by outlet, the same
 * grouping the on-screen chart draws (Index::outletChartData()).
 *
 * Filters arrive in the query string because the screen owns them — see
 * ConsolidatedStockTakeController's doc comment for why none of them are
 * trusted as-is.
 */
class StaffMealSummaryController extends Controller
{
    use ScopesToActiveOutlet;

    /** Records listed under each outlet before the block says "+N more". */
    private const DETAIL_ROWS = 15;

    /** Past this many records the per-outlet listing is dropped; the totals above stay exact regardless. */
    private const DETAIL_CEILING = 2000;

    public function __invoke(Request $request)
    {
        $data = $this->load($request);

        $pdf = Pdf::loadView('pdf.inventory-group-summary', $data)->setPaper('a4', 'portrait');

        return $pdf->download('Staff-Meal-Summary-' . $data['scope']['from'] . '-to-' . $data['scope']['to'] . '.pdf');
    }

    /** @return array<string, mixed> everything pdf.inventory-group-summary (and the Excel writer) need */
    protected function load(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $search = trim((string) $request->query('search', ''));
        $scoped = fn (): Builder => $this->scopedQuery($request, $from, $to, $search);

        $groups  = $this->byOutlet($scoped());
        $totals  = $this->totals($groups, $from, $to);
        $details = $this->details($scoped(), $groups, $totals['count']);

        $company = Company::find(Auth::user()->company_id);

        $scope = ['from' => $from, 'to' => $to];

        $scopeRows = collect([
            ['Outlet', ($id = $this->selectedOutletId($request->query('outlet'))) ? (Outlet::find($id)?->name ?? '—') : 'All outlets'],
            ['Search', $search !== '' ? '"' . $search . '"' : '—'],
        ]);

        return [
            'company'        => $company,
            'docTitle'       => 'Staff Meal Summary',
            'scope'          => $scope,
            'scopeRows'      => $scopeRows,
            'groupLabel'     => 'Outlet',
            'valueLabel'     => 'Cost',
            'noun'           => 'staff meal',
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
            ['label' => 'Date', 'width' => 82, 'value' => fn (StaffMealRecord $r) => $r->meal_date?->format('d M Y') ?? '—'],
            ['label' => 'Reference', 'width' => 140, 'value' => fn (StaffMealRecord $r) => $r->reference_number ?: '—'],
            ['label' => 'Items', 'align' => 'right', 'width' => 46, 'type' => 'number', 'value' => fn (StaffMealRecord $r) => $r->lines_count],
            ['label' => 'Cost (RM)', 'align' => 'right', 'width' => 78, 'type' => 'number', 'value' => fn (StaffMealRecord $r) => (float) $r->total_cost],
        ];
    }

    /** Mirrors App\Livewire\Inventory\Index::filtered() for the staff-meals tab. */
    private function scopedQuery(Request $request, string $from, string $to, string $search): Builder
    {
        $query = StaffMealRecord::query()->whereBetween('meal_date', [$from, $to]);

        $this->scopeByOutletFilter($query, $request->query('outlet'));

        if ($search !== '') {
            $query->where('reference_number', 'like', '%' . $search . '%');
        }

        return $query;
    }

    /** @return array<int, array{rank:int,name:string,anchor:string,color:string,count:int,value:float,share:float,group_key:int}> */
    private function byOutlet(Builder $query): array
    {
        $rows = $query
            ->selectRaw('outlet_id, COUNT(*) AS cnt, SUM(total_cost) AS cost')
            ->groupBy('outlet_id')
            ->get()
            ->filter(fn ($row) => (float) $row->cost > 0)
            ->sortByDesc('cost')
            ->values();

        $total = (float) $rows->sum('cost');
        $names = Outlet::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->whereIn('id', $rows->pluck('outlet_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(fn ($row, $i) => [
            'rank'      => $i + 1,
            'name'      => $names[$row->outlet_id] ?? '—',
            'anchor'    => 'group-' . ($i + 1),
            'color'     => PurchaseSupplierBreakdown::SERIES[$i] ?? PurchaseSupplierBreakdown::OTHER_COLOR,
            'count'     => (int) $row->cnt,
            'value'     => (float) $row->cost,
            'share'     => $total > 0 ? ((float) $row->cost / $total) * 100 : 0.0,
            'group_key' => (int) $row->outlet_id,
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
     * @param array<int, array<string, mixed>> $groups
     * @return array{omitted: bool, blocks: array<int, array<string, mixed>>}
     */
    private function details(Builder $query, array $groups, int $count): array
    {
        if ($count === 0 || $count > self::DETAIL_CEILING) {
            return ['omitted' => $count > 0, 'blocks' => []];
        }

        $rows = $query
            ->withCount('lines')
            ->orderByDesc('meal_date')
            ->orderByDesc('id')
            ->get();

        $grouped = $rows->groupBy('outlet_id');

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
