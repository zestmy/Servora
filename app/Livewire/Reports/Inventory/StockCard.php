<?php

namespace App\Livewire\Reports\Inventory;

use App\Models\Ingredient;
use App\Models\OutletTransferLine;
use App\Models\PurchaseRecordLine;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\WastageRecordLine;
use App\Services\UomService;
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
        $ingredient = $this->ingredientFilter ? $this->stockCardIngredient() : null;

        // The card is kept in one unit and every movement is converted into it,
        // so that is the unit to name — not the one the item is bought in.
        $stockUom = $this->ingredientFilter ? $this->stockUnit()?->abbreviation : null;

        return view('livewire.reports.inventory.stock-card', compact('movements', 'outlets', 'ingredients', 'ingredient', 'stockUom'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Stock Card']);
    }

    /**
     * The unit this card is kept in: the one the item is COUNTED in.
     *
     * A stock take sets the balance, so the balance can only mean something in
     * the unit a count is recorded in. Everything else is converted to it.
     */
    private function stockUnit(): ?UnitOfMeasure
    {
        $ingredient = $this->stockCardIngredient();

        return $ingredient?->recipeUom ?: $ingredient?->baseUom;
    }

    private ?Ingredient $memoIngredient = null;

    private function stockCardIngredient(): ?Ingredient
    {
        return $this->memoIngredient ??= Ingredient::with(['baseUom', 'recipeUom'])
            ->find($this->ingredientFilter);
    }

    /**
     * One unit for the whole card.
     *
     * The four sources each record in their own: a purchase arrives in the unit
     * it was bought in, a transfer moves in the base unit, wastage and counts
     * are in the recipe unit. They were added to one running balance as bare
     * numbers — two cartons plus five hundred grams minus three kilograms —
     * which is not a quantity of anything. Each is converted before it counts.
     */
    private function inStockUnit(float|string|null $quantity, ?int $fromUomId): float
    {
        $quantity = (float) $quantity;
        $target   = $this->stockUnit();

        if (! $target || ! $fromUomId || (int) $fromUomId === (int) $target->id) {
            return $quantity;
        }

        $source = $this->unitsById()->get((int) $fromUomId);

        if (! $source) {
            return $quantity;
        }

        return app(UomService::class)->convertQuantity(
            $quantity, $source, $target, $this->stockCardIngredient()?->id
        );
    }

    private ?Collection $memoUnits = null;

    private function unitsById(): Collection
    {
        return $this->memoUnits ??= UnitOfMeasure::all()->keyBy('id');
    }

    /**
     * The movement's date, as a date.
     *
     * Every source aliases its parent's date column onto a LINE model, and none
     * of those models cast an attribute called `date` — so it arrives as a
     * string and calling ->format() on it is fatal. The report could not render
     * for any item with a movement in range.
     */
    private function movementDate(mixed $value): \Illuminate\Support\Carbon
    {
        return $value instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($value)
            : \Illuminate\Support\Carbon::parse((string) $value);
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
            ->select('purchase_record_lines.quantity', 'purchase_record_lines.uom_id', 'pr.purchase_date as date', 'pr.reference_number')
            ->join('purchase_records as pr', 'pr.id', '=', 'purchase_record_lines.purchase_record_id')
            ->where('purchase_record_lines.ingredient_id', $ingredientId)
            ->whereBetween('pr.purchase_date', [$from, $to])
            ->whereNull('pr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('pr.outlet_id', $outletId))
            ->get();

        foreach ($purchases as $p) {
            $dp = $this->movementDate($p->date);

            $movements->push([
                'date' => $dp->format('Y-m-d'),
                'sort_date' => $dp,
                'reference' => 'PR: ' . $p->reference_number,
                'type' => 'IN',
                'quantity' => $this->inStockUnit($p->quantity, $p->uom_id),
            ]);
        }

        // Wastage Records (OUT)
        $wastage = WastageRecordLine::query()
            ->select('wastage_record_lines.quantity', 'wastage_record_lines.uom_id', 'wr.wastage_date as date', 'wr.reference_number')
            ->join('wastage_records as wr', 'wr.id', '=', 'wastage_record_lines.wastage_record_id')
            ->where('wastage_record_lines.ingredient_id', $ingredientId)
            ->whereBetween('wr.wastage_date', [$from, $to])
            ->whereNull('wr.deleted_at')
            ->when($outletId, fn ($q) => $q->where('wr.outlet_id', $outletId))
            ->get();

        foreach ($wastage as $w) {
            $dw = $this->movementDate($w->date);

            $movements->push([
                'date' => $dw->format('Y-m-d'),
                'sort_date' => $dw,
                'reference' => 'WST: ' . $w->reference_number,
                'type' => 'OUT',
                'quantity' => -$this->inStockUnit($w->quantity, $w->uom_id),
            ]);
        }

        // Outlet Transfers IN
        $transfersIn = OutletTransferLine::query()
            ->select('outlet_transfer_lines.quantity', 'outlet_transfer_lines.uom_id', 'ot.transfer_date as date', 'ot.transfer_number')
            ->join('outlet_transfers as ot', 'ot.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->where('outlet_transfer_lines.ingredient_id', $ingredientId)
            ->whereBetween('ot.transfer_date', [$from, $to])
            ->whereNull('ot.deleted_at')
            ->when($outletId, fn ($q) => $q->where('ot.to_outlet_id', $outletId))
            ->get();

        foreach ($transfersIn as $t) {
            $dt = $this->movementDate($t->date);

            $movements->push([
                'date' => $dt->format('Y-m-d'),
                'sort_date' => $dt,
                'reference' => 'TRF-IN: ' . $t->transfer_number,
                'type' => 'IN',
                'quantity' => $this->inStockUnit($t->quantity, $t->uom_id),
            ]);
        }

        // Outlet Transfers OUT
        $transfersOut = OutletTransferLine::query()
            ->select('outlet_transfer_lines.quantity', 'outlet_transfer_lines.uom_id', 'ot.transfer_date as date', 'ot.transfer_number')
            ->join('outlet_transfers as ot', 'ot.id', '=', 'outlet_transfer_lines.outlet_transfer_id')
            ->where('outlet_transfer_lines.ingredient_id', $ingredientId)
            ->whereBetween('ot.transfer_date', [$from, $to])
            ->whereNull('ot.deleted_at')
            ->when($outletId, fn ($q) => $q->where('ot.from_outlet_id', $outletId))
            ->get();

        foreach ($transfersOut as $t) {
            $dt = $this->movementDate($t->date);

            $movements->push([
                'date' => $dt->format('Y-m-d'),
                'sort_date' => $dt,
                'reference' => 'TRF-OUT: ' . $t->transfer_number,
                'type' => 'OUT',
                'quantity' => -$this->inStockUnit($t->quantity, $t->uom_id),
            ]);
        }

        // Stock Takes (SET - resets balance)
        $stockTakes = StockTakeLine::query()
            ->select('stock_take_lines.actual_quantity', 'stock_take_lines.uom_id', 'st.stock_take_date as date', 'st.reference_number')
            ->join('stock_takes as st', 'st.id', '=', 'stock_take_lines.stock_take_id')
            ->where('stock_take_lines.ingredient_id', $ingredientId)
            ->whereBetween('st.stock_take_date', [$from, $to])
            ->whereNull('st.deleted_at')
            ->when($outletId, fn ($q) => $q->where('st.outlet_id', $outletId))
            ->get();

        foreach ($stockTakes as $s) {
            $ds = $this->movementDate($s->date);

            $movements->push([
                'date' => $ds->format('Y-m-d'),
                'sort_date' => $ds,
                'reference' => 'ST: ' . $s->reference_number,
                'type' => 'COUNT',
                'quantity' => $this->inStockUnit($s->actual_quantity, $s->uom_id),
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
