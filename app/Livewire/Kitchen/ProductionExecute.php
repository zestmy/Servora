<?php

namespace App\Livewire\Kitchen;

use App\Models\KitchenInventory;
use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductionExecute extends Component
{
    public ProductionOrder $order;
    public array $actuals = [];

    public function mount(int $id): void
    {
        $this->order = ProductionOrder::with([
            'kitchen', 'lines.recipe.yieldUom', 'lines.recipe.ingredient',
            'lines.productionRecipe.yieldUom', 'lines.productionRecipe.ingredient',
            'lines.uom', 'lines.toOutlet',
        ])->findOrFail($id);

        if (! in_array($this->order->status, ['scheduled', 'in_progress'])) {
            session()->flash('error', 'This order cannot be executed.');
            $this->redirectRoute('kitchen.index');
            return;
        }

        if ($this->order->status === 'scheduled') {
            $this->order->update(['status' => 'in_progress', 'started_at' => now()]);
            $this->order->refresh();
        }

        foreach ($this->order->lines as $idx => $line) {
            $this->actuals[$idx] = (string) floatval($line->actual_quantity ?? $line->planned_quantity);
        }
    }

    /**
     * Persist entered actuals WITHOUT completing — a locked or swapped
     * tablet must not lose half-entered quantities (mount() restores them
     * from actual_quantity on reopen).
     */
    public function saveProgress(): void
    {
        $this->validate([
            'actuals'   => 'array',
            'actuals.*' => 'nullable|numeric|min:0',
        ]);

        foreach ($this->order->lines as $idx => $line) {
            if (isset($this->actuals[$idx]) && $this->actuals[$idx] !== '') {
                $line->update(['actual_quantity' => floatval($this->actuals[$idx])]);
            }
        }

        session()->flash('success', 'Progress saved — you can safely leave and continue later.');
    }

    public function complete(): void
    {
        $this->validate([
            'actuals'   => 'required|array',
            'actuals.*' => 'required|numeric|min:0',
        ]);

        if (! Auth::user()->canRunProduction($this->order->kitchen_id)) {
            session()->flash('error', 'Only kitchen managers and chefs can complete a production batch.');
            return;
        }

        DB::transaction(function () {
            $userId = Auth::id();
            $kitchenId = $this->order->kitchen_id;

            foreach ($this->order->lines as $idx => $line) {
                $actual  = floatval($this->actuals[$idx] ?? 0);
                $planned = floatval($line->planned_quantity);
                $variance = $planned > 0 ? (($actual - $planned) / $planned) * 100 : 0;
                $unitCost = floatval($line->unit_cost);

                $line->update(['actual_quantity' => $actual, 'status' => 'completed']);

                $batchNumber = $this->order->order_number . '-' . str_pad($idx + 1, 2, '0', STR_PAD_LEFT);

                ProductionLog::create([
                    'company_id'               => $this->order->company_id,
                    'production_order_id'      => $this->order->id,
                    'production_order_line_id' => $line->id,
                    'recipe_id'                => $line->recipe_id,
                    'production_recipe_id'     => $line->production_recipe_id,
                    'batch_number'             => $batchNumber,
                    'planned_yield'            => $planned,
                    'actual_yield'             => $actual,
                    'yield_variance_pct'       => round($variance, 2),
                    'uom_id'                   => $line->uom_id,
                    'total_cost'               => round($actual * $unitCost, 4),
                    'produced_by'              => $userId,
                    'produced_at'              => now(),
                ]);

                // Add to kitchen inventory (not auto-transfer). A line is
                // either a Central Kitchen production recipe or an outlet prep
                // item; each carries its own stockable ingredient.
                $stockItem = $line->productionRecipe?->ingredient ?? $line->recipe?->ingredient;
                if ($stockItem && $actual > 0) {
                    KitchenInventory::addStock($kitchenId, $stockItem->id, $actual, $line->uom_id, $unitCost);
                }
            }

            $this->order->update(['status' => 'completed', 'completed_at' => now()]);
        });

        session()->flash('success', "Production completed. Stock added to kitchen inventory.");
        $this->redirectRoute('kitchen.index');
    }

    public function render()
    {
        return view('livewire.kitchen.production-execute')
            ->layout('layouts.kitchen', ['title' => 'Execute: ' . $this->order->order_number]);
    }
}
