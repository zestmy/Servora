<?php

namespace App\Livewire\Kitchen;

use App\Models\KitchenInventory;
use App\Models\Outlet;
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

    /**
     * Raise one outlet transfer per destination for the lines that named one,
     * deducting what leaves from kitchen stock.
     *
     * @param  array<int, array<int, array>>  $bound  [outletId => [line, ...]]
     * @return array<int, string>  human-readable notes for the success flash
     */
    private function dispatchToOutlets(array $bound, ?int $userId): array
    {
        if (! $bound) return [];

        $kitchen = $this->order->kitchen;
        $fromOutletId = $kitchen?->outlet_id;

        // Without a base outlet there is nowhere to transfer FROM. The stock
        // stays in the kitchen rather than the completion failing — say so
        // instead of silently dropping the destination.
        if (! $fromOutletId) {
            return ['Destination outlets were skipped: this kitchen has no linked outlet to transfer from.'];
        }

        $notes = [];

        foreach ($bound as $outletId => $lines) {
            // Never transfer to itself, and never outside the company.
            if ((int) $outletId === (int) $fromOutletId) continue;
            $outlet = Outlet::withoutGlobalScopes()
                ->where('id', $outletId)
                ->where('company_id', $this->order->company_id)
                ->first();
            if (! $outlet) continue;

            $transfer = OutletTransfer::create([
                'company_id'      => $this->order->company_id,
                'from_outlet_id'  => $fromOutletId,
                'to_outlet_id'    => $outlet->id,
                'transfer_number' => $this->generateTransferNumber(),
                'status'          => 'in_transit',
                'transfer_date'   => now()->toDateString(),
                'notes'           => 'Auto-created on completing ' . $this->order->order_number,
                'created_by'      => $userId,
            ]);

            foreach ($lines as $line) {
                OutletTransferLine::create([
                    'outlet_transfer_id' => $transfer->id,
                    'ingredient_id'      => $line['ingredient_id'],
                    'quantity'           => $line['quantity'],
                    'uom_id'             => $line['uom_id'],
                    'unit_cost'          => $line['unit_cost'],
                ]);

                KitchenInventory::deductStock(
                    $this->order->kitchen_id, $line['ingredient_id'], $line['quantity']
                );
            }

            $notes[] = "Sent {$transfer->transfer_number} to {$outlet->name}.";
        }

        return $notes;
    }

    /** Matches the manual transfer form's numbering so the two interleave. */
    private function generateTransferNumber(): string
    {
        $prefix = 'TRF-' . now()->format('Ymd') . '-';
        $last   = OutletTransfer::withoutGlobalScopes()
            ->where('transfer_number', 'like', $prefix . '%')
            ->orderByDesc('transfer_number')
            ->value('transfer_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
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

        $transferSummary = [];

        DB::transaction(function () use (&$transferSummary) {
            $userId = Auth::id();
            $kitchenId = $this->order->kitchen_id;

            // Lines with a destination outlet are collected as we go and sent
            // on as ONE transfer per outlet at the end, rather than a separate
            // transfer per line.
            $bound = [];

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

                // Everything produced lands in kitchen stock first — a line
                // bound for an outlet is then transferred out of it below, so
                // the movement is visible rather than teleporting.
                $stockItem = $line->productionRecipe?->ingredient ?? $line->recipe?->ingredient;
                if ($stockItem && $actual > 0) {
                    KitchenInventory::addStock($kitchenId, $stockItem->id, $actual, $line->uom_id, $unitCost);

                    if ($line->to_outlet_id) {
                        $bound[(int) $line->to_outlet_id][] = [
                            'ingredient_id' => $stockItem->id,
                            'quantity'      => $actual,
                            'uom_id'        => $line->uom_id,
                            'unit_cost'     => $unitCost,
                        ];
                    }
                }
            }

            $transferSummary = $this->dispatchToOutlets($bound, $userId);

            $this->order->update(['status' => 'completed', 'completed_at' => now()]);
        });

        $message = 'Production completed. Stock added to kitchen inventory.';
        if ($transferSummary) {
            $message .= ' ' . implode(' ', $transferSummary);
        }
        session()->flash('success', $message);
        $this->redirectRoute('kitchen.index');
    }

    public function render()
    {
        return view('livewire.kitchen.production-execute')
            ->layout('layouts.kitchen', ['title' => 'Execute: ' . $this->order->order_number]);
    }
}
