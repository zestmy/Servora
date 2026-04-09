<?php

namespace App\Livewire\Purchasing;

use App\Models\DeliveryOrder;
use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRecord;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Services\OrderAdjustmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ReceiveForm extends Component
{
    // PO context (null = standalone delivery)
    public ?int    $poId         = null;
    public ?string $poNumber     = null;
    public ?string $poSupplier   = null;

    // Header
    public ?int   $supplier_id       = null;
    public string $doNumber          = '';
    public string $delivery_date     = '';
    public string $reference_number  = '';   // invoice / DO ref
    public string $notes             = '';
    public bool   $is_final_delivery = false;

    // Lines: [ingredient_id, ingredient_name, uom_id, uom_abbr, ordered_qty, received_qty, unit_cost, condition]
    public array $lines = [];

    // Ingredient search (standalone mode)
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'delivery_date'            => 'required|date',
            'lines'                    => 'required|array|min:1',
            'lines.*.received_qty'     => 'required|numeric|min:0',
            'lines.*.unit_cost'        => 'required|numeric|min:0',
            'lines.*.condition'        => 'required|in:good,damaged,rejected',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'                  => 'Add at least one ingredient line.',
            'lines.min'                       => 'Add at least one ingredient line.',
            'lines.*.received_qty.required'   => 'Received qty is required.',
            'lines.*.unit_cost.required'      => 'Unit cost is required.',
        ];
    }

    public function mount(?int $id = null): void   // $id = purchase_order_id
    {
        $this->delivery_date = now()->toDateString();
        $this->doNumber      = $this->generateDoNumber();

        if (! $id) return;

        $po = PurchaseOrder::with([
            'supplier',
            'lines.ingredient.baseUom',
            'lines.uom',
        ])->findOrFail($id);

        if ($po->outlet_id && ! Auth::user()->canAccessOutlet($po->outlet_id)) {
            abort(403, 'You do not have access to this outlet.');
        }

        $this->poId       = $po->id;
        $this->poNumber   = $po->po_number;
        $this->poSupplier = $po->supplier->name;
        $this->supplier_id = $po->supplier_id;

        $this->lines = $po->lines->map(fn ($l) => [
            'ingredient_id'       => $l->ingredient_id,
            'ingredient_name'     => $l->ingredient?->name ?? '—',
            'po_line_id'          => $l->id,
            'uom_id'              => $l->uom_id,
            'uom_abbr'            => $l->uom?->abbreviation ?? '',
            'ordered_qty'         => floatval($l->quantity),
            'previously_received' => floatval($l->received_quantity),
            'remaining_qty'       => floatval($l->quantity - $l->received_quantity),
            'received_qty'        => (string) max(0, floatval($l->quantity - $l->received_quantity)),
            'unit_cost'           => (string) floatval($l->unit_cost),
            'condition'           => 'good',
        ])->toArray();
    }

    // ── Standalone mode: add ingredients manually ─────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::with(['baseUom'])->find($ingredientId);
        if (! $ingredient) return;

        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'ingredient_id'   => $ingredientId,
            'ingredient_name' => $ingredient->name,
            'uom_id'          => $ingredient->base_uom_id,
            'uom_abbr'        => $ingredient->baseUom?->abbreviation ?? '',
            'ordered_qty'     => 0,
            'received_qty'    => '1',
            'unit_cost'       => (string) floatval($ingredient->purchase_price),
            'condition'       => 'good',
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function confirm(): void
    {
        if (! Auth::user()->hasCapability('can_receive_grn')) {
            session()->flash('error', 'You do not have permission to receive goods.');
            return;
        }

        $this->validate();

        DB::transaction(function () {
            $companyId = Auth::user()->company_id;
            $outletId  = Auth::user()->activeOutletId()
                ?: Outlet::where('company_id', $companyId)->value('id');

            // 1. Create Delivery Order
            $deliverySeq = $this->poId
                ? OrderAdjustmentService::nextDeliverySequence($this->poId)
                : 1;

            $do = DeliveryOrder::create([
                'company_id'        => $companyId,
                'outlet_id'         => $outletId,
                'purchase_order_id' => $this->poId,
                'supplier_id'       => $this->supplier_id,
                'do_number'         => $this->doNumber,
                'status'            => 'received',
                'delivery_sequence' => $deliverySeq,
                'is_final_delivery' => $this->is_final_delivery,
                'delivery_date'     => $this->delivery_date,
                'notes'             => $this->notes ?: null,
                'received_by'       => Auth::id(),
            ]);

            $recordTotal = 0;

            foreach ($this->lines as $line) {
                $received  = floatval($line['received_qty']);
                $unitCost  = floatval($line['unit_cost']);
                $condition = $line['condition'];

                // DO line
                $do->lines()->create([
                    'purchase_order_line_id' => $line['po_line_id'] ?? null,
                    'ingredient_id'          => $line['ingredient_id'],
                    'ordered_quantity'       => $line['ordered_qty'],
                    'delivered_quantity'     => $received,
                    'uom_id'                => $line['uom_id'],
                    'unit_cost'             => $unitCost,
                    'condition'             => $condition,
                ]);

                if ($condition !== 'rejected' && $received > 0) {
                    $recordTotal += $received * $unitCost;
                }

                // Update ingredient cost when received in good condition
                if ($condition === 'good' && $received > 0) {
                    $ingredient = Ingredient::find($line['ingredient_id']);
                    if ($ingredient && abs(floatval($ingredient->purchase_price) - $unitCost) > 0.0001) {
                        $oldCost = floatval($ingredient->purchase_price);
                        $yieldFactor = max(floatval($ingredient->yield_percent), 0.01) / 100;

                        $ingredient->update([
                            'purchase_price' => $unitCost,
                            'current_cost'   => round($unitCost / $yieldFactor, 4),
                        ]);

                        IngredientPriceHistory::create([
                            'ingredient_id'  => $ingredient->id,
                            'supplier_id'    => $this->supplier_id,
                            'cost'           => $unitCost,
                            'uom_id'         => $line['uom_id'],
                            'effective_date' => $this->delivery_date,
                            'source'         => 'purchase_record',
                        ]);
                    }
                }
            }

            // 2. Update PO received quantities and overall status
            if ($this->poId) {
                $this->updatePoStatus();
            }

            // 3. Create Purchase Record
            $pr = PurchaseRecord::create([
                'company_id'        => $companyId,
                'outlet_id'         => $outletId,
                'supplier_id'       => $this->supplier_id,
                'delivery_order_id' => $do->id,
                'reference_number'  => $this->reference_number ?: null,
                'purchase_date'     => $this->delivery_date,
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

        session()->flash('success', 'Delivery received. Purchase record created and ingredient costs updated.');
        $this->redirectRoute('purchasing.index');
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $uoms      = UnitOfMeasure::orderBy('name')->get();

        $searchResults = collect();
        if (! $this->poId && strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::with(['baseUom'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $grandTotal = collect($this->lines)->reduce(function ($carry, $line) {
            $received = floatval($line['received_qty'] ?? 0);
            $cost     = floatval($line['unit_cost'] ?? 0);
            return $carry + ($line['condition'] !== 'rejected' ? $received * $cost : 0);
        }, 0.0);

        $pageTitle = $this->poId
            ? 'Receive Delivery: ' . $this->poNumber
            : 'Record Direct Purchase';

        // Check if this PO already has partial deliveries
        $hasPartialDeliveries = false;
        $deliveryCount = 0;
        if ($this->poId) {
            $deliveryCount = DeliveryOrder::where('purchase_order_id', $this->poId)->count();
            $hasPartialDeliveries = $deliveryCount > 0;
        }

        return view('livewire.purchasing.receive-form', compact(
            'suppliers', 'uoms', 'searchResults', 'grandTotal', 'hasPartialDeliveries', 'deliveryCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function updatePoStatus(): void
    {
        $po = PurchaseOrder::with('lines')->findOrFail($this->poId);

        // Add received quantities from this delivery
        foreach ($this->lines as $line) {
            $poLine = $po->lines->firstWhere('ingredient_id', $line['ingredient_id']);
            if ($poLine) {
                $added       = floatval($line['received_qty']);
                $newReceived = floatval($poLine->received_quantity) + $added;
                // Don't cap at ordered qty — allow over-delivery
                $poLine->update(['received_quantity' => $newReceived]);
            }
        }

        $po->refresh();
        $allReceived = $po->lines->every(fn ($l) => floatval($l->received_quantity) >= floatval($l->quantity));
        $anyReceived = $po->lines->some(fn ($l) => floatval($l->received_quantity) > 0);

        // If marked as final delivery, close the PO regardless of quantities
        if ($this->is_final_delivery) {
            $po->update(['status' => 'received']);
        } else {
            $po->update(['status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : 'sent')]);
        }
    }

    private function generateDoNumber(): string
    {
        $prefix = 'DO-' . now()->format('Ymd') . '-';
        $last   = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')
            ->value('do_number');
        $seq    = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
