<?php

namespace App\Livewire\Kitchen;

use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductionExecute extends Component
{
    public ProductionOrder $order;

    // Actual quantities keyed by line index
    public array $actuals = [];

    public function mount(int $id): void
    {
        $this->order = ProductionOrder::with([
            'kitchen', 'lines.recipe.yieldUom', 'lines.recipe.ingredient', 'lines.uom', 'lines.toOutlet',
        ])->findOrFail($id);

        if (! in_array($this->order->status, ['scheduled', 'in_progress'])) {
            session()->flash('error', 'This order cannot be executed (status: ' . $this->order->status . ').');
            $this->redirectRoute('kitchen.index');
            return;
        }

        // Mark as in_progress if still scheduled
        if ($this->order->status === 'scheduled') {
            $this->order->update(['status' => 'in_progress', 'started_at' => now()]);
            $this->order->refresh();
        }

        // Initialise actuals array
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
            $companyId = $this->order->company_id;
            $kitchenOutletId = $this->order->kitchen?->outlet_id;

            foreach ($this->order->lines as $idx => $line) {
                $actual  = floatval($this->actuals[$idx] ?? 0);
                $planned = floatval($line->planned_quantity);
                $variance = $planned > 0 ? (($actual - $planned) / $planned) * 100 : 0;

                // Update line
                $line->update([
                    'actual_quantity' => $actual,
                    'status'          => 'completed',
                ]);

                // Create production log
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
                    'total_cost'               => round($actual * floatval($line->unit_cost), 4),
                    'produced_by'              => $userId,
                    'produced_at'              => now(),
                ]);

                // Create outlet transfer if destination outlet is set
                if ($line->to_outlet_id && $kitchenOutletId) {
                    $transferNumber = 'KT-' . now()->format('Ymd') . '-' . $batchNumber;

                    $transfer = OutletTransfer::create([
                        'company_id'     => $companyId,
                        'from_outlet_id' => $kitchenOutletId,
                        'to_outlet_id'   => $line->to_outlet_id,
                        'transfer_number' => $transferNumber,
                        'status'         => 'draft',
                        'transfer_date'  => now()->toDateString(),
                        'notes'          => "Auto-created from production order {$this->order->order_number}",
                        'created_by'     => $userId,
                    ]);

                    // Add the prep ingredient to the transfer
                    $prepIngredient = $line->recipe?->ingredient;
                    if ($prepIngredient) {
                        OutletTransferLine::create([
                            'outlet_transfer_id' => $transfer->id,
                            'ingredient_id'      => $prepIngredient->id,
                            'quantity'            => $actual,
                            'uom_id'             => $line->uom_id,
                            'unit_cost'          => floatval($line->unit_cost),
                        ]);
                    }
                }
            }

            // Complete the order
            $this->order->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        });

        session()->flash('success', "Production order {$this->order->order_number} completed.");
        $this->redirectRoute('kitchen.index');
    }

    public function render()
    {
        return view('livewire.kitchen.production-execute')
            ->layout('layouts.app', ['title' => 'Execute: ' . $this->order->order_number]);
    }
}
