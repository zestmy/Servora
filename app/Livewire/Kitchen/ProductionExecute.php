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
            'kitchen', 'lines.recipe.yieldUom', 'lines.recipe.ingredient', 'lines.uom', 'lines.toOutlet',
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

    public function complete(): void
    {
        $this->validate([
            'actuals'   => 'required|array',
            'actuals.*' => 'required|numeric|min:0',
        ]);

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
                    'production_order_id'      => $this->order->id,
                    'production_order_line_id' => $line->id,
                    'recipe_id'                => $line->recipe_id,
                    'batch_number'             => $batchNumber,
                    'planned_yield'            => $planned,
                    'actual_yield'             => $actual,
                    'yield_variance_pct'       => round($variance, 2),
                    'uom_id'                   => $line->uom_id,
                    'total_cost'               => round($actual * $unitCost, 4),
                    'produced_by'              => $userId,
                    'produced_at'              => now(),
                ]);

                // Add to kitchen inventory (not auto-transfer)
                $prepIngredient = $line->recipe?->ingredient;
                if ($prepIngredient && $actual > 0) {
                    KitchenInventory::addStock($kitchenId, $prepIngredient->id, $actual, $line->uom_id, $unitCost);
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
