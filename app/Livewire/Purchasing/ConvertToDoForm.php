<?php

namespace App\Livewire\Purchasing;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ConvertToDoForm extends Component
{
    public int $poId;
    public string $poNumber = '';
    public string $outletName = '';
    public string $supplierName = '';

    public string $delivery_date = '';
    public string $notes = '';

    // Lines: [ingredient_id, ingredient_name, quantity, uom_id, uom_abbr, unit_cost, total_cost, po_quantity]
    public array $lines = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'delivery_date'          => 'required|date',
            'lines'                  => 'required|array|min:1',
            'lines.*.ingredient_id'  => 'required|exists:ingredients,id',
            'lines.*.quantity'       => 'required|numeric|min:0.0001',
            'lines.*.uom_id'        => 'required|exists:units_of_measure,id',
            'lines.*.unit_cost'      => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'            => 'Add at least one item.',
            'lines.min'                 => 'Add at least one item.',
            'lines.*.quantity.min'      => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(int $id): void
    {
        $po = PurchaseOrder::with([
            'outlet', 'supplier', 'lines.ingredient.baseUom', 'lines.uom',
        ])->findOrFail($id);

        if ($po->status !== 'approved') {
            session()->flash('error', 'Only approved POs can be converted to DO.');
            $this->redirectRoute('purchasing.index');
            return;
        }

        $this->poId         = $po->id;
        $this->poNumber     = $po->po_number;
        $this->outletName   = $po->outlet?->name ?? '—';
        $this->supplierName = $po->supplier?->name ?? '—';
        $this->delivery_date = now()->addDays(1)->toDateString();
        $this->notes        = $po->notes ?? '';

        $this->lines = $po->lines->map(function ($l) use ($po) {
            $packSize = $this->getPackSize($l->ingredient_id, $po->supplier_id);
            $packInfo = '';
            if ($packSize > 1 && $l->ingredient?->baseUom) {
                $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
                $packInfo = '(' . $formatted . ' ' . strtoupper($l->ingredient->baseUom->abbreviation) . '/PACK)';
            }
            return [
                'ingredient_id'   => $l->ingredient_id,
                'ingredient_name' => $l->ingredient?->name ?? '—',
                'quantity'        => (string) floatval($l->quantity),
                'uom_id'          => $l->uom_id,
                'uom_abbr'        => $l->uom?->abbreviation ?? '',
                'unit_cost'       => (string) floatval($l->unit_cost),
                'total_cost'      => round(floatval($l->quantity) * floatval($l->unit_cost), 4),
                'po_quantity'     => floatval($l->quantity),
                'pack_info'       => $packInfo,
            ];
        })->toArray();
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
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
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
            'uom_abbr'        => $ingredient->baseUom?->abbreviation ?? '',
            'unit_cost'       => (string) floatval($ingredient->purchase_price),
            'total_cost'      => floatval($ingredient->purchase_price),
            'po_quantity'     => 0,
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) === 2 && in_array($parts[1], ['quantity', 'unit_cost'])) {
            $this->recalcLine((int) $parts[0]);
        }
    }

    public function convert(): void
    {
        $this->validate();

        DB::transaction(function () {
            $po = PurchaseOrder::with('outlet')->findOrFail($this->poId);
            $companyId = $po->company_id;
            $outletId  = $po->outlet_id;

            // 1. Create Delivery Order
            $doNumber = $this->generateDoNumber();
            $total = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));

            $do = DeliveryOrder::create([
                'company_id'        => $companyId,
                'outlet_id'         => $outletId,
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'do_number'         => $doNumber,
                'status'            => 'pending',
                'delivery_date'     => $this->delivery_date,
                'notes'             => $this->notes ?: null,
                'received_by'       => null,
                'created_by'        => Auth::id(),
            ]);

            foreach ($this->lines as $line) {
                $qty  = floatval($line['quantity']);
                $cost = floatval($line['unit_cost']);
                $do->lines()->create([
                    'ingredient_id'     => $line['ingredient_id'],
                    'ordered_quantity'   => $qty,
                    'delivered_quantity' => 0,
                    'uom_id'            => $line['uom_id'],
                    'unit_cost'         => $cost,
                    'condition'         => 'good',
                ]);
            }

            // 2. Auto-generate GRN for outlet
            $grnNumber = $this->generateGrnNumber();
            $grn = GoodsReceivedNote::create([
                'company_id'        => $companyId,
                'outlet_id'         => $outletId,
                'delivery_order_id' => $do->id,
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'grn_number'        => $grnNumber,
                'status'            => 'pending',
                'total_amount'      => round($total, 4),
                'created_by'        => Auth::id(),
            ]);

            foreach ($this->lines as $line) {
                $qty  = floatval($line['quantity']);
                $cost = floatval($line['unit_cost']);
                $grn->lines()->create([
                    'ingredient_id'     => $line['ingredient_id'],
                    'expected_quantity'  => $qty,
                    'received_quantity'  => 0,
                    'uom_id'            => $line['uom_id'],
                    'unit_cost'         => $cost,
                    'total_cost'        => round($qty * $cost, 4),
                    'condition'         => 'good',
                ]);
            }

            // 3. Update PO status to sent (purchasing has processed it)
            $po->update(['status' => 'sent']);
        });

        session()->flash('success', 'DO created and GRN generated for outlet receiving.');
        $this->redirectRoute('purchasing.index', ['tab' => 'do']);
    }

    public function render()
    {
        $uoms = UnitOfMeasure::orderBy('name')->get();
        $grandTotal = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::with('baseUom')
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $showPrice = (bool) Company::find(Auth::user()->company_id)?->show_price_on_do_grn;

        return view('livewire.purchasing.convert-to-do', compact(
            'uoms', 'grandTotal', 'searchResults', 'showPrice'
        ))->layout('layouts.app', ['title' => 'Convert PO to DO: ' . $this->poNumber]);
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;
        $qty  = floatval($this->lines[$idx]['quantity'] ?? 0);
        $cost = floatval($this->lines[$idx]['unit_cost'] ?? 0);
        $this->lines[$idx]['total_cost'] = round($qty * $cost, 4);
    }

    private function getPackSize(int $ingredientId, int $supplierId): float
    {
        $packSize = DB::table('supplier_ingredients')
            ->where('supplier_id', $supplierId)
            ->where('ingredient_id', $ingredientId)
            ->value('pack_size');

        return floatval($packSize ?? 1) ?: 1;
    }

    private function generateDoNumber(): string
    {
        $prefix = 'DO-' . now()->format('Ymd') . '-';
        $last = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->orderByDesc('do_number')->value('do_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    private function generateGrnNumber(): string
    {
        $prefix = 'GRN-' . now()->format('Ymd') . '-';
        $last = GoodsReceivedNote::where('grn_number', 'like', $prefix . '%')
            ->orderByDesc('grn_number')->value('grn_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
