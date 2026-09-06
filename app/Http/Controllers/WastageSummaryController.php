<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\WastageRecord;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Wastage in a range, totalled per department and filed as a PDF.
 *
 * The Wastage tab answers "what did we throw out" one row at a time; this is
 * the same range added up by department, the same grouping the on-screen
 * chart already draws (Index::departmentChartData()), so the export and the
 * chart can never disagree about which department wasted how much.
 *
 * Filters arrive in the query string because the screen owns them — see
 * ConsolidatedStockTakeController's doc comment for why none of them are
 * trusted as-is.
 */
class WastageSummaryController extends Controller
{
    use ScopesToActiveOutlet;

    /** Wastage notes listed under each department before the block says "+N more". */
    private const DETAIL_ROWS = 15;

    /** Past this many notes the per-department listing is dropped; the totals above stay exact regardless. */
    private const DETAIL_CEILING = 2000;

    public function __invoke(Request $request)
    {
        $data = $this->load($request);

        $pdf = Pdf::loadView('pdf.inventory-group-summary', $data)->setPaper('a4', 'portrait');

        return $pdf->download('Wastage-Summary-' . $data['scope']['from'] . '-to-' . $data['scope']['to'] . '.pdf');
    }

    /** @return array<string, mixed> everything pdf.inventory-group-summary (and the Excel writer) need */
    protected function load(Request $request): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $department = (string) $request->query('department', '');
        $search     = trim((string) $request->query('search', ''));

        $scoped = fn (): Builder => $this->scopedQuery($request, $from, $to, $department, $search);

        $groups  = $this->byDepartment($scoped());
        $totals  = $this->totals($groups, $from, $to);
        $details = $this->details($scoped(), $groups, $totals['count']);

        $company = Company::find(Auth::user()->company_id);

        $scope = [
            'from' => $from,
            'to'   => $to,
        ];

        $scopeRows = collect([
            ['Outlet', ($id = $this->selectedOutletId($request->query('outlet'))) ? (Outlet::find($id)?->name ?? '—') : 'All outlets'],
            ['Department', $department === 'none' ? 'No department' : ($department !== '' ? (Department::find((int) $department)?->name ?? '—') : 'All departments')],
            ['Search', $search !== '' ? '"' . $search . '"' : '—'],
        ]);

        return [
            'company'       => $company,
            'docTitle'      => 'Wastage Summary',
            'scope'         => $scope,
            'scopeRows'     => $scopeRows,
            'groupLabel'    => 'Department',
            'valueLabel'    => 'Cost',
            'noun'          => 'wastage record',
            'groups'        => $groups,
            'totals'        => $totals,
            'detailBlocks'  => $details['blocks'],
            'omittedDetails'=> $details['omitted'],
            'detailColumns' => $this->detailColumns(),
            'companyName'   => $company?->name,
            'generatedBy'   => Auth::user()->name,
        ];
    }

    /** @return array<int, array{label:string,align?:string,width?:int,type?:string,value:\Closure}> */
    private function detailColumns(): array
    {
        return [
            ['label' => 'Date', 'width' => 82, 'value' => fn (WastageRecord $r) => $r->wastage_date?->format('d M Y') ?? '—'],
            ['label' => 'Reference', 'width' => 108, 'value' => fn (WastageRecord $r) => $r->reference_number ?: '—'],
            ['label' => 'Reason(s)', 'value' => fn (WastageRecord $r) => $r->lines->pluck('reason')->filter()->unique()->implode(', ') ?: '—'],
            ['label' => 'Items', 'align' => 'right', 'width' => 46, 'type' => 'number', 'value' => fn (WastageRecord $r) => $r->lines->count()],
            ['label' => 'Cost (RM)', 'align' => 'right', 'width' => 78, 'type' => 'number', 'value' => fn (WastageRecord $r) => (float) $r->total_cost],
        ];
    }

    /** Mirrors App\Livewire\Inventory\Index::filtered() for the wastage tab. */
    private function scopedQuery(Request $request, string $from, string $to, string $department, string $search): Builder
    {
        $query = WastageRecord::query()->whereBetween('wastage_date', [$from, $to]);

        $this->scopeByOutletFilter($query, $request->query('outlet'));

        if ($department === 'none') {
            $query->whereNull('department_id');
        } elseif ($department !== '') {
            $query->where('department_id', (int) $department);
        }

        if ($search !== '') {
            $query->where('reference_number', 'like', '%' . $search . '%');
        }

        return $query;
    }

    /** @return array<int, array{rank:int,name:string,anchor:string,color:string,count:int,value:float,share:float}> */
    private function byDepartment(Builder $query): array
    {
        $rows = $query
            ->selectRaw('department_id, COUNT(*) AS cnt, SUM(total_cost) AS cost')
            ->groupBy('department_id')
            ->get()
            ->filter(fn ($row) => (float) $row->cost > 0)
            ->sortByDesc('cost')
            ->values();

        $total = (float) $rows->sum('cost');
        $names = Department::whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');

        return $rows->map(function ($row, $i) use ($total, $names) {
            return [
                'rank'         => $i + 1,
                'name'         => $row->department_id ? ($names[$row->department_id] ?? '—') : 'No department',
                'anchor'       => 'group-' . ($i + 1),
                'color'        => \App\Services\PurchaseSupplierBreakdown::SERIES[$i] ?? \App\Services\PurchaseSupplierBreakdown::OTHER_COLOR,
                'count'        => (int) $row->cnt,
                'value'        => (float) $row->cost,
                'share'        => $total > 0 ? ((float) $row->cost / $total) * 100 : 0.0,
                'group_key'    => $row->department_id ?: 'none',
            ];
        })->values()->all();
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
            ->with(['lines:id,wastage_record_id,reason'])
            ->orderByDesc('wastage_date')
            ->orderByDesc('id')
            ->get();

        // Same key each group was aggregated under in byDepartment(), so a
        // block's rows and its own header total can never drift apart.
        $grouped = $rows->groupBy(fn (WastageRecord $r) => $r->department_id ?: 'none');

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
