<?php

namespace App\Livewire\Reports\Inventory;

use App\Models\Ingredient;
use App\Models\OutletTransferLine;
use App\Models\PurchaseRecordLine;
use App\Models\StockTakeLine;
use App\Models\WastageRecordLine;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class StockCard extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public ?int $ingredientFilter = null;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function updatedIngredientFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv()
    {
        $movements = $this->buildMovements();

        return $this->exportCsvDownload('stock-card.csv', [
            'Date', 'Reference', 'Type', 'Quantity', 'Running Balance',
        ], $movements->map(fn ($m) => [
            $m['date'], $m['reference'], $m['type'], $m['quantity'], $m['balance'],
        ])->toArray());
    }

    public function render()
    {
        $movements = $this->ingredientFilter ? $this->buildMovements() : collect();
        $outlets = $this->getOutlets();
        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();
        $ingredient = $this->ingredientFilter ? Ingredient::with('baseUom')->find($this->ingredientFilter) : null;

        return view('livewire.reports.inventory.stock-card', compact('movements', 'outlets', 'ingredients', 'ingredient'))
            ->layout('layouts.app', ['title' => 'Stock Card']);
    }

    private function buildMovements(): Collection
    {
        $from = $this->dateFrom;
        $to = $this->dateTo;
        $ingredientId = $this->ingredientFilter;
        $outletId = $this->outletFilter;

        $movements = collect();

        // Purchase Records (IN)
        $purchases = PurchaseRecordLine::query()
            ->select('purchase_record_lines.quantity', 'pr.purchase_date as date', 'pr.reference_number')
            ->join('purchase_records as pr', 'pr.id', '=', 'purchase_record_lines.purchase_record_id')
            ->where('purchase_record_lines.ingredient_id', $ingredientId)
            ->whereBetween('pr.purchase_date', [$from, $to])
            ->whereNull('pr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('pr.outlet_id', $outletId))
            ->get();

        foreach ($purchases as $p) {
            $movements->push([
                'date' => $p->date->format('Y-m-d'),
                'sort_date' => $p->date,
                'reference' => 'PR: ' . $p->reference_number,
                'type' => 'IN',
                'quantity' => (float) $p->quantity,
            ]);
        }

        // Wastage Records (OUT)
        $wastage = WastageRecordLine::query()
            ->select('wastage_record_lines.quantity', 'wr.wastage_date as date', 'wr.reference_number')
            ->join('wastage_records as wr', 'wr.id', '=', 'wastage_record_lines.wastage_record_id')
            ->where('wastage_record_lines.ingredient_id', $ingredientId)
            ->whereBetween('wr.wastage_date', [$from, $to])
            ->whereNull('wr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('wr.outlet_id', $outletId))
            ->get();

        foreach ($wastage as $w) {
            $movements->push([
                'date' => $w->date->format('Y-m-d'),
                'sort_date' => $w->date,
                'reference' => 'WST: ' . $w->reference_number,
                'type' => 'OUT',
                'quantity' => -(float) $w->quantity,
            ]);
        }

        // Outlet Transfers IN
        $transfersIn = OutletTransferLine::query()
            ->select('outlet_transfer_lines.quantity', 'ot.transfer_date as date', 'ot.transfer_number')
            ->join('outlet_transfers as ot', 'ot.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->where('outlet_transfer_lines.ingredient_id', $ingredientId)
            ->whereBetween('ot.transfer_date', [$from, $to])
            ->whereNull('ot.deleted_at')
            ->when($outletId, fn ($q) => $q->where('ot.to_outlet_id', $outletId))
            ->get();

        foreach ($transfersIn as $t) {
            $movements->push([
                'date' => $t->date->format('Y-m-d'),
                'sort_date' => $t->date,
                'reference' => 'TRF-IN: ' . $t->transfer_number,
                'type' => 'IN',
                'quantity' => (float) $t->quantity,
            ]);
        }

        // Outlet Transfers OUT
        $transfersOut = OutletTransferLine::query()
            ->select('outlet_transfer_lines.quantity', 'ot.transfer_date as date', 'ot.transfer_number')
            ->join('outlet_transfers as ot', 'ot.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->where('outlet_transfer_lines.ingredient_id', $ingredientId)
            ->whereBetween('ot.transfer_date', [$from, $to])
            ->whereNull('ot.deleted_at')
            ->when($outletId, fn ($q) => $q->where('ot.from_outlet_id', $outletId))
            ->get();

        foreach ($transfersOut as $t) {
            $movements->push([
                'date' => $t->date->format('Y-m-d'),
                'sort_date' => $t->date,
                'reference' => 'TRF-OUT: ' . $t->transfer_number,
                'type' => 'OUT',
                'quantity' => -(float) $t->quantity,
            ]);
        }

        // Stock Takes (SET - resets balance)
        $stockTakes = StockTakeLine::query()
            ->select('stock_take_lines.actual_quantity', 'st.stock_take_date as date', 'st.reference_number')
            ->join('stock_takes as st', 'st.id', '=', 'stock_take_lines.stock_take_id')
            ->where('stock_take_lines.ingredient_id', $ingredientId)
            ->whereBetween('st.stock_take_date', [$from, $to])
            ->whereNull('st.deleted_at')
            ->when($outletId, fn ($q) => $q->where('st.outlet_id', $outletId))
            ->get();

        foreach ($stockTakes as $s) {
            $movements->push([
                'date' => $s->date->format('Y-m-d'),
                'sort_date' => $s->date,
                'reference' => 'ST: ' . $s->reference_number,
                'type' => 'COUNT',
                'quantity' => (float) $s->actual_quantity,
            ]);
        }

        // Sort by date
        $movements = $movements->sortBy('date')->values();

        // Calculate running balance
        $balance = 0;
        return $movements->map(function ($m) use (&$balance) {
            if ($m['type'] === 'COUNT') {
                $balance = $m['quantity'];
            } else {
                $balance += $m['quantity'];
            }
            $m['balance'] = round($balance, 4);
            return $m;
        });
    }
}
