<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\Supplier;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Captured purchases in a range, totalled per supplier and filed as a PDF.
 *
 * The Purchases tab answers "what did we buy" one row at a time. The question
 * behind it is almost always "who are we actually paying" — which the list can
 * only answer by being read and added up. This is that addition, with the shape
 * of it drawn rather than left to be inferred from a column of numbers.
 *
 * Filters arrive in the query string because the screen owns them: the button
 * hands over exactly what the table is showing, so the file matches what was on
 * screen when it was asked for. None of them are trusted — the outlet goes
 * through the same accessible-outlets check the listing uses, so a hand-edited
 * id narrows the report or does nothing, and never widens it.
 */
class PurchaseSupplierSummaryController extends Controller
{
    use ScopesToActiveOutlet;

    /**
     * Series colours for the charts.
     *
     * Hex, because a PDF is rendered by dompdf and never sees a Tailwind class —
     * this is the one place the palette cannot come from `tailwind.config.js`.
     * It opens on the brand teal and then walks deliberately AWAY from the
     * semantic hues: no green, amber or red, because on a spend chart those read
     * as "good", "watch" and "over budget" rather than as "supplier 4".
     */
    private const SERIES = [
        '#0b7677', '#43bdb8', '#1d4ed8', '#7c3aed', '#0891b2',
        '#be185d', '#4338ca', '#0f766e', '#6d28d9', '#155e75',
    ];

    /** Everything past the tenth supplier is drawn as one grey band. */
    private const OTHER_COLOR = '#94a3b8';

    /** Suppliers drawn in their own colour before the rest becomes "Other". */
    private const CHART_SLICES = 10;

    /**
     * Purchases listed under each supplier before the block says "+N more".
     *
     * The most recent fifteen, not all of them. This is a summary — a busy
     * supplier with two hundred captures would bury the charts it belongs to
     * under its own register, and the total above the list already covers
     * every one of them.
     */
    private const DETAIL_ROWS = 15;

    /**
     * Past this many purchases the per-supplier listing is dropped.
     *
     * The summary above it is aggregated in SQL and is always complete, so what
     * gets left out is detail, never a total. Truncating the money silently is
     * the one thing a report like this must not do.
     */
    private const DETAIL_CEILING = 2000;

    public function __invoke(Request $request)
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $department = (string) $request->query('department', '');
        $supplierId = (string) $request->query('supplier', '');
        $search     = trim((string) $request->query('search', ''));

        $scoped = fn (): Builder => $this->scopedQuery($request, $from, $to, $department, $supplierId, $search);

        $suppliers = $this->bySupplier($scoped());
        $totals    = $this->totals($suppliers, $from, $to);
        $months    = $this->byMonth($scoped());
        $details   = $this->details($scoped(), $suppliers, $totals['purchases']);

        $company = Company::find(Auth::user()->company_id);

        $scope = [
            'from'       => $from,
            'to'         => $to,
            'outlet'     => ($id = $this->selectedOutletId($request->query('outlet')))
                ? Outlet::find($id)?->name
                : 'All outlets',
            'department' => $department === 'none'
                ? 'No department'
                : ($department !== ''
                    ? (Department::find((int) $department)?->name ?? '—')
                    : 'All departments'),
            'supplier'   => $supplierId !== ''
                ? (Supplier::find((int) $supplierId)?->name ?? '—')
                : 'All suppliers',
            'search'     => $search,
        ];

        $pdf = Pdf::loadView('pdf.purchase-supplier-summary', [
            'company'    => $company,
            'scope'      => $scope,
            'totals'     => $totals,
            'suppliers'  => $suppliers,
            'months'     => $months,
            'details'    => $details,
            'exportedBy' => Auth::user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Purchases-by-Supplier-' . $from . '-to-' . $to . '.pdf');
    }

    /**
     * The same rows the Purchases tab is showing.
     *
     * Mirrors App\Livewire\Inventory\Index::filtered() for the purchases tab —
     * outlet, department, supplier, search and the date range — so the file and
     * the screen cannot disagree about which purchases are in scope.
     */
    private function scopedQuery(
        Request $request,
        string $from,
        string $to,
        string $department,
        string $supplierId,
        string $search,
    ): Builder {
        $query = PurchaseCapture::query()->whereBetween('purchase_date', [$from, $to]);

        $this->scopeByOutletFilter($query, $request->query('outlet'));

        if ($department === 'none') {
            $query->whereNull('department_id');
        } elseif ($department !== '') {
            $query->where('department_id', (int) $department);
        }

        if ($supplierId !== '') {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('reference_number', 'like', '%' . $search . '%')
                ->orWhere('supplier_name', 'like', '%' . $search . '%'));
        }

        return $query;
    }

    /**
     * Spend per supplier, biggest first.
     *
     * Grouped in SQL on the two columns a purchase can name a supplier with, so
     * the totals hold however many rows the range covers. A capture either
     * points at a Supplier record or carries a typed name, and the same vendor
     * can arrive both ways — so the SQL groups are merged in PHP on the name,
     * which is what the reader recognises. Only MIN/MAX/SUM/COUNT are used: no
     * date functions, because those are the part that differs between MySQL in
     * production and SQLite under test.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bySupplier(Builder $query): array
    {
        $rows = $query
            ->selectRaw('supplier_id, supplier_name, COUNT(*) AS purchases, SUM(amount) AS spend, MIN(purchase_date) AS first_at, MAX(purchase_date) AS last_at')
            ->groupBy('supplier_id', 'supplier_name')
            ->get();

        $names = Supplier::whereIn('id', $rows->pluck('supplier_id')->filter()->unique())
            ->pluck('name', 'id');

        $merged = [];

        foreach ($rows as $row) {
            $name = $row->supplier_id
                ? ($names[$row->supplier_id] ?? trim((string) $row->supplier_name))
                : trim((string) $row->supplier_name);

            $name = $name !== '' ? $name : 'Unspecified supplier';
            $key  = mb_strtolower($name);

            $merged[$key] ??= [
                'name'        => $name,
                'supplier_id' => $row->supplier_id,
                'purchases'   => 0,
                'spend'       => 0.0,
                'first_at'    => (string) $row->first_at,
                'last_at'     => (string) $row->last_at,
            ];

            $merged[$key]['purchases'] += (int) $row->purchases;
            $merged[$key]['spend']     += (float) $row->spend;
            $merged[$key]['first_at']  = min($merged[$key]['first_at'], (string) $row->first_at);
            $merged[$key]['last_at']   = max($merged[$key]['last_at'], (string) $row->last_at);
        }

        $total = array_sum(array_column($merged, 'spend'));

        return collect($merged)
            ->sortByDesc('spend')
            ->values()
            ->map(function (array $row, int $i) use ($total) {
                $row['rank']    = $i + 1;
                $row['anchor']  = 'supplier-' . ($i + 1);
                $row['share']   = $total > 0 ? ($row['spend'] / $total) * 100 : 0.0;
                $row['average'] = $row['purchases'] > 0 ? $row['spend'] / $row['purchases'] : 0.0;
                $row['color']   = $i < self::CHART_SLICES ? self::SERIES[$i] : self::OTHER_COLOR;

                return $row;
            })
            ->all();
    }

    /**
     * The cards across the top.
     *
     * @param  array<int, array<string, mixed>>  $suppliers
     * @return array<string, mixed>
     */
    private function totals(array $suppliers, string $from, string $to): array
    {
        $spend     = array_sum(array_column($suppliers, 'spend'));
        $purchases = array_sum(array_column($suppliers, 'purchases'));
        $days      = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $top       = $suppliers[0] ?? null;

        // How much of the spend the biggest three account for. One supplier's
        // share reads as a fluke on a short range; the top three is the number
        // that says whether this kitchen has a concentration problem.
        $topThree = array_sum(array_column(array_slice($suppliers, 0, 3), 'spend'));

        return [
            'spend'         => $spend,
            'purchases'     => $purchases,
            'suppliers'     => count($suppliers),
            'average'       => $purchases > 0 ? $spend / $purchases : 0.0,
            'perDay'        => $days > 0 ? $spend / $days : 0.0,
            'days'          => $days,
            'topName'       => $top['name'] ?? '—',
            'topSpend'      => $top['spend'] ?? 0.0,
            'topShare'      => $top['share'] ?? 0.0,
            'topThreeShare' => $spend > 0 ? ($topThree / $spend) * 100 : 0.0,
        ];
    }

    /**
     * Spend per calendar month, oldest first.
     *
     * Grouped on the date column in SQL and bucketed into months here, rather
     * than with DATE_FORMAT() or MONTH() — those are MySQL spellings, and a
     * report written in them cannot be tested against SQLite.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byMonth(Builder $query): array
    {
        $rows = $query
            ->selectRaw('purchase_date, SUM(amount) AS spend')
            ->groupBy('purchase_date')
            ->get();

        $buckets = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row->purchase_date);
            $key  = $date->format('Y-m');

            $buckets[$key] ??= ['key' => $key, 'label' => $date->format('M y'), 'spend' => 0.0];
            $buckets[$key]['spend'] += (float) $row->spend;
        }

        ksort($buckets);

        $peak = max([0.0, ...array_column($buckets, 'spend')]);

        return collect($buckets)
            ->map(function (array $bucket) use ($peak) {
                // Height is against the tallest column, not against the total:
                // a chart whose biggest bar is 8% tall shows nothing.
                $bucket['height'] = $peak > 0 ? ($bucket['spend'] / $peak) * 100 : 0.0;

                return $bucket;
            })
            ->values()
            ->all();
    }

    /**
     * Each supplier's own purchases, for the block its chart row links to.
     *
     * @param  array<int, array<string, mixed>>  $suppliers
     * @return array{omitted: bool, blocks: array<int, array<string, mixed>>}
     */
    private function details(Builder $query, array $suppliers, int $purchaseCount): array
    {
        if ($purchaseCount === 0 || $purchaseCount > self::DETAIL_CEILING) {
            return ['omitted' => $purchaseCount > 0, 'blocks' => []];
        }

        $rows = $query
            ->with(['supplier:id,name', 'department:id,name', 'outlet:id,name'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get(['id', 'purchase_date', 'amount', 'reference_number', 'supplier_id', 'supplier_name', 'department_id', 'outlet_id']);

        // Keyed the way bySupplier() merges its groups, so a vendor that appears
        // both linked and hand-typed lands in one block.
        $grouped = $rows->groupBy(function (PurchaseCapture $row) {
            $name = $row->supplier?->name ?: trim((string) $row->supplier_name);

            return mb_strtolower($name !== '' ? $name : 'Unspecified supplier');
        });

        $blocks = [];

        foreach ($suppliers as $supplier) {
            $own = $grouped->get(mb_strtolower($supplier['name']), collect());

            $blocks[] = [
                'supplier' => $supplier,
                'rows'     => $own->take(self::DETAIL_ROWS),
                'more'     => max(0, $own->count() - self::DETAIL_ROWS),
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
