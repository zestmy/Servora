<?php

namespace App\Livewire\Reports\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockBalanceProduct extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function exportCsv()
    {
        $rows = $this->buildQuery()->get();

        return $this->exportCsvDownload('stock-balance-product.csv', [
            'Ingredient', 'Category', 'Opening', 'Purchases', 'Transfers In', 'Transfers Out',
            'Wastage', 'Closing Balance', 'Value',
        ], $rows->map(fn ($r) => [
            $r->name, $r->category_name, $r->opening_qty, $r->purchases_qty,
            $r->transfers_in, $r->transfers_out, $r->wastage_qty, $r->closing_balance,
            number_format($r->closing_value, 2),
        ])->toArray());
    }

    public function render()
    {
        $items = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();

        return view('livewire.reports.inventory.stock-balance-product', compact('items', 'outlets'))
            ->layout('layouts.app', ['title' => 'Stock Balance (Product)']);
    }

    private function buildQuery()
    {
        $from = $this->dateFrom;
        $to = $this->dateTo;
        $outletId = $this->outletFilter;

        // Opening qty: latest stock take actual_quantity BEFORE dateFrom
        $openingSub = DB::raw("(
            SELECT stl.actual_quantity
            FROM stock_take_lines stl
            JOIN stock_takes st ON st.id = stl.stock_take_id
            WHERE stl.ingredient_id = ingredients.id
            " . ($outletId ? "AND st.outlet_id = {$outletId}" : "") . "
            AND st.stock_take_date < '{$from}'
            ORDER BY st.stock_take_date DESC, stl.id DESC
            LIMIT 1
        )");

        // Purchases in period
        $purchasesSub = DB::raw("(
            SELECT COALESCE(SUM(prl.quantity), 0)
            FROM purchase_record_lines prl
            JOIN purchase_records pr ON pr.id = prl.purchase_record_id
            WHERE prl.ingredient_id = ingredients.id
            " . ($outletId ? "AND pr.outlet_id = {$outletId}" : "") . "
            AND pr.purchase_date BETWEEN '{$from}' AND '{$to}'
            AND pr.deleted_at IS NULL
        )");

        // Transfers in
        $transfersInSub = DB::raw("(
            SELECT COALESCE(SUM(otl.quantity), 0)
            FROM outlet_transfer_lines otl
            JOIN outlet_transfers ot ON ot.id = otl.outlet_transfer_id
            WHERE otl.ingredient_id = ingredients.id
            " . ($outletId ? "AND ot.to_outlet_id = {$outletId}" : "") . "
            AND ot.transfer_date BETWEEN '{$from}' AND '{$to}'
            AND ot.deleted_at IS NULL
        )");

        // Transfers out
        $transfersOutSub = DB::raw("(
            SELECT COALESCE(SUM(otl.quantity), 0)
            FROM outlet_transfer_lines otl
            JOIN outlet_transfers ot ON ot.id = otl.outlet_transfer_id
            WHERE otl.ingredient_id = ingredients.id
            " . ($outletId ? "AND ot.from_outlet_id = {$outletId}" : "") . "
            AND ot.transfer_date BETWEEN '{$from}' AND '{$to}'
            AND ot.deleted_at IS NULL
        )");

        // Wastage
        $wastageSub = DB::raw("(
            SELECT COALESCE(SUM(wrl.quantity), 0)
            FROM wastage_record_lines wrl
            JOIN wastage_records wr ON wr.id = wrl.wastage_record_id
            WHERE wrl.ingredient_id = ingredients.id
            " . ($outletId ? "AND wr.outlet_id = {$outletId}" : "") . "
            AND wr.wastage_date BETWEEN '{$from}' AND '{$to}'
            AND wr.deleted_at IS NULL
        )");

        return Ingredient::query()
            ->select([
                'ingredients.id', 'ingredients.name', 'ingredients.current_cost',
                'ic.name as category_name',
                DB::raw("COALESCE({$openingSub}, 0) as opening_qty"),
                DB::raw("{$purchasesSub} as purchases_qty"),
                DB::raw("{$transfersInSub} as transfers_in"),
                DB::raw("{$transfersOutSub} as transfers_out"),
                DB::raw("{$wastageSub} as wastage_qty"),
                DB::raw("(COALESCE({$openingSub}, 0) + {$purchasesSub} + {$transfersInSub} - {$transfersOutSub} - {$wastageSub}) as closing_balance"),
                DB::raw("(COALESCE({$openingSub}, 0) + {$purchasesSub} + {$transfersInSub} - {$transfersOutSub} - {$wastageSub}) * ingredients.current_cost as closing_value"),
            ])
            ->leftJoin('ingredient_categories as ic', 'ic.id', '=', 'ingredients.ingredient_category_id')
            ->where('ingredients.is_active', true)
            ->orderBy('ingredients.name');
    }
}
