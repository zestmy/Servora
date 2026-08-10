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
    public function updatedOutletFilter(): void { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }

    protected function getOutlets()
    {
        return Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)->orderBy('name')->get();
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
