<?php

namespace App\Traits;

use App\Models\Outlet;
use App\Models\Supplier;
use App\Services\CsvExportService;
use Illuminate\Support\Facades\Auth;

trait ReportFilters
{
    /*
     * Every report gets the named ranges, from the one definition the rest of
     * the product uses. Twenty-one screens share this trait, and each of them
     * had two bare date boxes and a month-to-date default — so "last week" on a
     * report meant typing two dates while the list screen beside it had a
     * button for it.
     */
    use HasQuickDateRanges;

    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $outletFilter = null;
    public ?int $supplierFilter = null;

    public function mountReportFilters(): void
    {
        $this->bootQuickRange();
    }

    /**
     * Reports opened month-to-date before the named ranges arrived, and still
     * do — a report is usually read against the month you are closing.
     */
    protected function defaultQuickRange(): string
    {
        return 'this_month';
    }

    // A typed date is nobody's named range any more.
    public function updatedDateFrom(): void { $this->quickRange = ''; $this->resetPage(); }
    public function updatedDateTo(): void { $this->quickRange = ''; $this->resetPage(); }
    public function updatedOutletFilter(): void
    {
        // An outlet id arriving from the browser must not reach past the
        // outlets this user may see: drop it rather than trust it.
        if (! empty($this->outletFilter) && $this->reportOutletId() === null) {
            $this->outletFilter = null;
        }
        $this->resetPage();
    }
    public function updatedSupplierFilter(): void { $this->resetPage(); }

    /**
     * The outlet dropdown: only outlets this user may see. It used to list
     * every outlet of the company, so an outlet-restricted manager could pick
     * (and read) a sibling outlet's figures.
     */
    protected function getOutlets()
    {
        return Auth::user()->accessibleOutlets()
            ->where('outlets.is_active', true)
            ->orderBy('outlets.name')
            ->get();
    }

    /*
     * ── Tenant and outlet bounds for report queries ─────────────────────
     *
     * Line tables (stock_take_lines, sales_record_lines, purchase_order_lines,
     * ingredient_price_history …) have no company column and no global scope,
     * and a raw join never applies the parent model's scope. So every report
     * that reads through a join has to bound the company-owned PARENT table
     * itself. Without it, "no outlet filter" meant every company's rows.
     */

    /** The active company every report is bounded to. */
    protected function reportCompanyId(): int
    {
        return (int) Auth::user()->company_id;
    }

    /**
     * The outlet filter, only when this user may actually see that outlet.
     * A tampered or stale id reads as "no filter", never as a wider reach.
     */
    protected function reportOutletId(): ?int
    {
        if (empty($this->outletFilter)) {
            return null;
        }

        $id = (int) $this->outletFilter;

        return in_array($id, Auth::user()->accessibleOutletIds(), true) ? $id : null;
    }

    /**
     * Outlets an outlet-restricted user is limited to, or null when the user
     * sees every outlet of the company (no bound needed beyond the company).
     */
    protected function reportRestrictedOutletIds(): ?array
    {
        $user = Auth::user();

        return $user->canViewAllOutlets() ? null : $user->accessibleOutletIds();
    }

    /**
     * Bound a query's outlet column to what the user may see, then narrow to
     * the (validated) outlet filter. Works on Eloquent and query builders.
     */
    protected function applyReportOutletScope($query, string $column)
    {
        $restricted = $this->reportRestrictedOutletIds();
        if ($restricted !== null) {
            $query->whereIn($column, $restricted);
        }

        if (($id = $this->reportOutletId()) !== null) {
            $query->where($column, $id);
        }

        return $query;
    }

    /**
     * The same bound as raw SQL, for the correlated subqueries some reports
     * build by hand. Everything interpolated is an integer.
     */
    protected function reportOutletSql(string $column): string
    {
        $sql = '';

        $restricted = $this->reportRestrictedOutletIds();
        if ($restricted !== null) {
            $sql .= $restricted === []
                ? ' AND 1 = 0'
                : ' AND ' . $column . ' IN (' . implode(',', array_map('intval', $restricted)) . ')';
        }

        if (($id = $this->reportOutletId()) !== null) {
            $sql .= ' AND ' . $column . ' = ' . $id;
        }

        return $sql;
    }

    /**
     * A report date safe to place in raw SQL: Y-m-d, or the fallback. dateFrom
     * and dateTo are public Livewire properties, which is to say browser input.
     */
    protected function reportDate(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
    }

    protected function getSuppliers()
    {
        return Supplier::where('is_active', true)->orderBy('name')->get();
    }

    protected function exportCsvDownload(string $filename, array $headers, $rows)
    {
        return CsvExportService::download($filename, $headers, $rows);
    }
}
