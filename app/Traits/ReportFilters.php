<?php

namespace App\Traits;

use App\Models\Outlet;
use App\Models\Supplier;
use App\Services\CsvExportService;
use Illuminate\Support\Facades\Auth;

trait ReportFilters
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $outletFilter = null;
    public ?int $supplierFilter = null;

    public function mountReportFilters(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }
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
