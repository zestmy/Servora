<?php

namespace App\Livewire\Purchasing;

use App\Models\Company;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GrnReceiveForm extends Component
{
    public int $grnId;
    public string $grnNumber = '';
    public string $doNumber = '';
    public string $poNumber = '';
    public string $outletName = '';
    public string $supplierName = '';
    public string $status = 'pending';

    public string $received_date = '';
    public string $reference_number = '';
    public string $notes = '';

    // Lines: [id, ingredient_id, ingredient_name, uom_id, uom_abbr, expected_qty, received_qty, unit_cost, condition]
    public array $lines = [];

    protected function rules(): array
    {
        return [
            'received_date'            => 'required|date',
            'lines'                    => 'required|array|min:1',
            'lines.*.received_qty'     => 'required|numeric|min:0',
            'lines.*.unit_cost'        => 'required|numeric|min:0',
            'lines.*.condition'        => 'required|in:good,damaged,rejected',
        ];
    }

    public function mount(int $id): void
    {
        $grn = GoodsReceivedNote::with([
            'lines.ingredient.baseUom', 'lines.uom',
            'deliveryOrder', 'purchaseOrder', 'outlet', 'supplier',
        ])->findOrFail($id);

        if ($grn->status !== 'pending') {
            session()->flash('error', 'This GRN has already been processed.');
            $this->redirectRoute('purchasing.index', ['tab' => 'grn']);
            return;
        }

        $this->grnId        = $grn->id;
        $this->grnNumber    = $grn->grn_number;
        $this->doNumber     = $grn->deliveryOrder?->do_number ?? '—';
        $this->poNumber     = $grn->purchaseOrder?->po_number ?? '—';
        $this->outletName   = $grn->outlet?->name ?? '—';
        $this->supplierName = $grn->supplier?->name ?? '—';
        $this->status       = $grn->status;
        $this->received_date = now()->toDateString();
        $this->notes        = $grn->notes ?? '';

        $this->lines = $grn->lines->map(function ($l) use ($grn) {
            $packSize = $this->getPackSize($l->ingredient_id, $grn->supplier_id);
            $packInfo = '';
            if ($packSize > 1 && $l->ingredient?->baseUom) {
                $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
                $packInfo = '(' . $formatted . ' ' . strtoupper($l->ingredient->baseUom->abbreviation) . '/PACK)';
            }
            return [
                'id'              => $l->id,
                'ingredient_id'   => $l->ingredient_id,
                'ingredient_name' => $l->ingredient?->name ?? '—',
                'uom_id'          => $l->uom_id,
                'uom_abbr'        => $l->uom?->abbreviation ?? '',
                'expected_qty'    => floatval($l->expected_quantity),
                'received_qty'    => (string) floatval($l->expected_quantity),
                'unit_cost'       => (string) floatval($l->unit_cost),
                'condition'       => 'good',
                'pack_info'       => $packInfo,
            ];
        })->toArray();
    }

    public function confirm(): void
    {
        $this->validate();

        DB::transaction(function () {
            $grn = GoodsReceivedNote::with(['deliveryOrder', 'purchaseOrder'])->findOrFail($this->grnId);
            $companyId = $grn->company_id;
            $outletId  = $grn->outlet_id;

            $recordTotal = 0;

            // 1. Update GRN lines with received data
            foreach ($this->lines as $line) {
                $grnLine = $grn->lines()->find($line['id']);
                if (! $grnLine) continue;

                $received  = floatval($line['received_qty']);
                $unitCost  = floatval($line['unit_cost']);
                $condition = $line['condition'];

                $grnLine->update([
                    'received_quantity' => $received,
                    'unit_cost'         => $unitCost,
                    'total_cost'        => round($received * $unitCost, 4),
                    'condition'         => $condition,
                ]);

                if ($condition !== 'rejected' && $received > 0) {
                    $recordTotal += $received * $unitCost;
                }

                // Update ingredient cost when received in good condition
                if ($condition === 'good' && $received > 0) {
                    $ingredient = Ingredient::find($line['ingredient_id']);
                    if ($ingredient) {
                        $packSize = $this->getPackSize($ingredient->id, $grn->supplier_id);
                        $yieldFactor = max(floatval($ingredient->yield_percent), 0.01) / 100;
                        $baseCost = $unitCost / max($packSize, 0.0001);

                        // purchase_price = pack price, pack_size from supplier, current_cost derived
                        $ingredient->update([
                            'purchase_price' => $unitCost,
                            'pack_size'      => $packSize,
                            'current_cost'   => round($baseCost / $yieldFactor, 4),
                        ]);

                        // Update supplier_ingredients.last_cost
                        DB::table('supplier_ingredients')
                            ->where('supplier_id', $grn->supplier_id)
                            ->where('ingredient_id', $ingredient->id)
                            ->update(['last_cost' => $unitCost]);

                        IngredientPriceHistory::create([
                            'ingredient_id'  => $ingredient->id,
                            'supplier_id'    => $grn->supplier_id,
                            'cost'           => $unitCost,
                            'uom_id'         => $line['uom_id'],
                            'effective_date' => $this->received_date,
                            'source'         => 'grn_receive',
                        ]);
                    }
                }
            }

            // 2. Update GRN status
            $grn->update([
                'status'        => 'received',
                'received_date' => $this->received_date,
                'total_amount'  => round($recordTotal, 4),
                'notes'         => $this->notes ?: null,
                'received_by'   => Auth::id(),
            ]);

            // 3. Update DO status and lines
            if ($grn->deliveryOrder) {
                $do = $grn->deliveryOrder;
                foreach ($this->lines as $line) {
                    $doLine = $do->lines()->where('ingredient_id', $line['ingredient_id'])->first();
                    if ($doLine) {
                        $doLine->update([
                            'delivered_quantity' => floatval($line['received_qty']),
                            'condition'          => $line['condition'],
                        ]);
                    }
                }
                $do->update([
                    'status'      => 'received',
                    'received_by' => Auth::id(),
                ]);
            }

            // 4. Update PO received quantities and status
            if ($grn->purchaseOrder) {
                $po = $grn->purchaseOrder->load('lines');
                foreach ($this->lines as $line) {
                    $poLine = $po->lines->firstWhere('ingredient_id', $line['ingredient_id']);
                    if ($poLine) {
                        $added = floatval($line['received_qty']);
                        $newReceived = min(
                            floatval($poLine->received_quantity) + $added,
                            floatval($poLine->quantity)
                        );
                        $poLine->update(['received_quantity' => $newReceived]);
                    }
                }

                $po->refresh();
                $allReceived = $po->lines->every(fn ($l) => floatval($l->received_quantity) >= floatval($l->quantity));
                $anyReceived = $po->lines->some(fn ($l) => floatval($l->received_quantity) > 0);
                $po->update(['status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : 'sent')]);
            }

            // 5. Create Purchase Record
            $pr = PurchaseRecord::create([
                'company_id'        => $companyId,
                'outlet_id'         => $outletId,
                'supplier_id'       => $grn->supplier_id,
                'delivery_order_id' => $grn->delivery_order_id,
                'reference_number'  => $this->reference_number ?: null,
                'purchase_date'     => $this->received_date,
                'total_amount'      => round($recordTotal, 4),
                'notes'             => $this->notes ?: null,
                'created_by'        => Auth::id(),
            ]);

            foreach ($this->lines as $line) {
                $received = floatval($line['received_qty']);
                $unitCost = floatval($line['unit_cost']);
                if ($line['condition'] !== 'rejected' && $received > 0) {
                    $pr->lines()->create([
                        'ingredient_id' => $line['ingredient_id'],
                        'quantity'      => $received,
                        'uom_id'        => $line['uom_id'],
                        'unit_cost'     => $unitCost,
                        'total_cost'    => round($received * $unitCost, 4),
                    ]);
                }
            }
        });

        session()->flash('success', 'GRN received. Purchase record created and ingredient costs updated.');
        $this->redirectRoute('purchasing.index', ['tab' => 'grn']);
    }

    public function render()
    {
        $grandTotal = collect($this->lines)->reduce(function ($carry, $line) {
            $received = floatval($line['received_qty'] ?? 0);
            $cost     = floatval($line['unit_cost'] ?? 0);
            return $carry + ($line['condition'] !== 'rejected' ? $received * $cost : 0);
        }, 0.0);

        $showPrice = (bool) Company::find(Auth::user()->company_id)?->show_price_on_do_grn;

        return view('livewire.purchasing.grn-receive-form', compact('grandTotal', 'showPrice'))
            ->layout('layouts.app', ['title' => 'Receive GRN: ' . $this->grnNumber]);
    }

    private function getPackSize(int $ingredientId, int $supplierId): float
    {
        $packSize = DB::table('supplier_ingredients')
            ->where('supplier_id', $supplierId)
            ->where('ingredient_id', $ingredientId)
            ->value('pack_size');

        return floatval($packSize ?? 1) ?: 1;
    }
}
