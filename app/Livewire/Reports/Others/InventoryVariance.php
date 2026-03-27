<?php

namespace App\Livewire\Reports\Others;

use App\Models\Ingredient;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryVariance extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('inventory-variance.csv', [
            'Ingredient', 'Expected Qty', 'Actual Qty', 'Variance Qty', 'Variance %', 'Value Impact',
        ], $rows->map(fn ($r) => [
            $r->name, $r->expected_qty, $r->actual_qty,
            $r->variance_qty, $r->variance_pct, number_format($r->value_impact, 2),
        ])->toArray());
    }

    public function render()
    {
        $items = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.others.inventory-variance', compact('items', 'outlets'))
            ->layout('layouts.app', ['title' => 'Inventory Variance']);
    }

    private function buildQuery()
    {
        $from = $this->dateFrom;
        $to = $this->dateTo;
        $outletId = $this->outletFilter;

        // Expected = opening + purchases - wastage - sales usage (simplified: purchases - wastage)
        $purchasesSub = DB::raw("(
            SELECT COALESCE(SUM(prl.quantity), 0)
            FROM purchase_record_lines prl
            JOIN purchase_records pr ON pr.id = prl.purchase_record_id
            WHERE prl.ingredient_id = ingredients.id
            " . ($outletId ? "AND pr.outlet_id = {$outletId}" : "") . "
            AND pr.purchase_date BETWEEN '{$from}' AND '{$to}'
            AND pr.deleted_at IS NULL
        )");

        $wastageSub = DB::raw("(
            SELECT COALESCE(SUM(wrl.quantity), 0)
            FROM wastage_record_lines wrl
            JOIN wastage_records wr ON wr.id = wrl.wastage_record_id
            WHERE wrl.ingredient_id = ingredients.id
            " . ($outletId ? "AND wr.outlet_id = {$outletId}" : "") . "
            AND wr.wastage_date BETWEEN '{$from}' AND '{$to}'
            AND wr.deleted_at IS NULL
        )");

        // Actual = latest stock take count in period
        $actualSub = DB::raw("(
            SELECT stl.actual_quantity
            FROM stock_take_lines stl
            JOIN stock_takes st ON st.id = stl.stock_take_id
            WHERE stl.ingredient_id = ingredients.id
            " . ($outletId ? "AND st.outlet_id = {$outletId}" : "") . "
            AND st.stock_take_date BETWEEN '{$from}' AND '{$to}'
            AND st.deleted_at IS NULL
            ORDER BY st.stock_take_date DESC, stl.id DESC
            LIMIT 1
        )");

        // Opening = latest stock take before period
        $openingSub = DB::raw("(
            SELECT stl2.actual_quantity
            FROM stock_take_lines stl2
            JOIN stock_takes st2 ON st2.id = stl2.stock_take_id
            WHERE stl2.ingredient_id = ingredients.id
            " . ($outletId ? "AND st2.outlet_id = {$outletId}" : "") . "
            AND st2.stock_take_date < '{$from}'
            AND st2.deleted_at IS NULL
            ORDER BY st2.stock_take_date DESC, stl2.id DESC
            LIMIT 1
        )");

        return Ingredient::query()
            ->select([
                'ingredients.id', 'ingredients.name', 'ingredients.current_cost',
                DB::raw("(COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub}) as expected_qty"),
                DB::raw("COALESCE({$actualSub}, 0) as actual_qty"),
                DB::raw("(COALESCE({$actualSub}, 0) - (COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub})) as variance_qty"),
                DB::raw("CASE WHEN (COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub}) != 0
                    THEN ROUND(((COALESCE({$actualSub}, 0) - (COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub})) / (COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub})) * 100, 2)
                    ELSE 0 END as variance_pct"),
                DB::raw("(COALESCE({$actualSub}, 0) - (COALESCE({$openingSub}, 0) + {$purchasesSub} - {$wastageSub})) * ingredients.current_cost as value_impact"),
            ])
            ->where('ingredients.is_active', true)
            ->having('actual_qty', '!=', 0)
            ->orHaving('expected_qty', '!=', 0)
            ->orderBy('ingredients.name');
    }
}
