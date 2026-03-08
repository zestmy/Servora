<?php

namespace App\Livewire\Purchasing;

use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OrderForm extends Component
{
    public ?int $orderId = null;

    // Read-only header info
    public string $poNumber = '';
    public string $status   = 'draft';

    // Editable header
    public ?int   $supplier_id              = null;
    public ?int   $ingredient_category_id   = null;
    public string $order_date               = '';
    public string $expected_delivery_date   = '';
    public string $notes                    = '';

    // Lines: [ingredient_id, ingredient_name, quantity, uom_id, unit_cost, total_cost]
    public array  $lines              = [];
    public string $ingredientSearch   = '';
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        return [
            'supplier_id'            => 'required|exists:suppliers,id',
            'ingredient_category_id' => 'nullable|exists:ingredient_categories,id',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'notes'                  => 'nullable|string',
            'lines'                  => 'required|array|min:1',
            'lines.*.ingredient_id'  => 'required|exists:ingredients,id',
            'lines.*.quantity'       => 'required|numeric|min:0.0001',
            'lines.*.uom_id'         => 'required|exists:units_of_measure,id',
            'lines.*.unit_cost'      => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'supplier_id.required'          => 'Please select a supplier.',
            'lines.required'                => 'Add at least one ingredient.',
            'lines.min'                     => 'Add at least one ingredient.',
            'lines.*.quantity.min'          => 'Quantity must be greater than zero.',
            'lines.*.unit_cost.required'    => 'Unit cost is required.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->order_date = now()->toDateString();

        if (! $id) {
            $this->poNumber = $this->generatePoNumber();
            return;
        }

        $po = PurchaseOrder::with(['lines.ingredient.baseUom', 'lines.uom'])->findOrFail($id);

        $this->orderId                = $po->id;
        $this->poNumber               = $po->po_number;
        $this->status                 = $po->status;
        $this->supplier_id            = $po->supplier_id;
        $this->ingredient_category_id = $po->ingredient_category_id;
        $this->order_date             = $po->order_date->toDateString();
        $this->expected_delivery_date = $po->expected_delivery_date?->toDateString() ?? '';
        $this->notes                  = $po->notes ?? '';

        $this->lines = $po->lines->map(fn ($l) => [
            'ingredient_id'   => $l->ingredient_id,
            'ingredient_name' => $l->ingredient?->name ?? '—',
            'quantity'        => (string) floatval($l->quantity),
            'uom_id'          => $l->uom_id,
            'unit_cost'       => (string) floatval($l->unit_cost),
            'total_cost'      => round(floatval($l->quantity) * floatval($l->unit_cost), 4),
        ])->toArray();
    }

    public function updatedSupplierId(): void
    {
        // Re-price existing lines from the newly selected supplier's catalog
        foreach ($this->lines as $idx => $line) {
            $this->lines[$idx]['unit_cost'] = (string) $this->lookupUnitCost(
                (int) $line['ingredient_id'], $this->supplier_id
            );
            $this->recalcLine($idx);
        }
    }

    // ── Load from template ────────────────────────────────────────────────

    public function loadTemplate(): void
    {
        if (! $this->selectedTemplateId) return;

        $template = FormTemplate::with([
            'lines.ingredient.baseUom',
        ])->find((int) $this->selectedTemplateId);

        if (! $template) {
            $this->selectedTemplateId = '';
            return;
        }

        $existing = collect($this->lines)->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();
        $added = 0;

        foreach ($template->lines as $tLine) {
            if ($tLine->item_type !== 'ingredient' || ! $tLine->ingredient) continue;
            if (in_array($tLine->ingredient_id, $existing)) continue;

            $unitCost = $this->lookupUnitCost($tLine->ingredient_id, $this->supplier_id);
            $qty      = max(0.001, $tLine->default_quantity);

            $this->lines[] = [
                'ingredient_id'   => $tLine->ingredient_id,
                'ingredient_name' => $tLine->ingredient->name,
                'quantity'        => (string) $qty,
                'uom_id'          => $tLine->ingredient->base_uom_id,
                'unit_cost'       => (string) $unitCost,
                'total_cost'      => round($qty * $unitCost, 4),
            ];

            $existing[] = $tLine->ingredient_id;
            $added++;
        }

        $this->selectedTemplateId = '';

        if ($added === 0) {
            session()->flash('info', 'All items from that template are already in the order.');
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $unitCost = $this->lookupUnitCost($ingredientId, $this->supplier_id);

        $this->lines[] = [
            'ingredient_id'   => $ingredientId,
            'ingredient_name' => $ingredient->name,
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
            'unit_cost'       => (string) $unitCost,
            'total_cost'      => $unitCost,
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

    public function save(string $action = 'save'): void
    {
        $this->validate();

        $user     = Auth::user();
        $total    = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));
        $outletId = $user->activeOutletId() ?? Outlet::where('company_id', $user->company_id)->value('id');

        $requiresApproval = $user->company?->require_po_approval ?? true;

        if ($action === 'submit') {
            $status = $requiresApproval ? 'submitted' : 'approved';
        } else {
            $status = $this->status;
        }

        $data = [
            'supplier_id'            => $this->supplier_id,
            'ingredient_category_id' => $this->ingredient_category_id,
            'order_date'             => $this->order_date,
            'expected_delivery_date' => $this->expected_delivery_date ?: null,
            'notes'                  => $this->notes ?: null,
            'total_amount'           => $total,
            'status'                 => $status,
        ];

        // Auto-approve: set approved_by to the submitter
        if ($action === 'submit' && ! $requiresApproval) {
            $data['approved_by'] = Auth::id();
        }

        if ($this->orderId) {
            $po = PurchaseOrder::findOrFail($this->orderId);
            $po->update($data);
        } else {
            $data['company_id'] = $user->company_id;
            $data['outlet_id']  = $outletId;
            $data['po_number']  = $this->poNumber;
            $data['created_by'] = Auth::id();
            $po = PurchaseOrder::create($data);
        }

        // Sync lines
        $po->lines()->delete();
        foreach ($this->lines as $line) {
            $qty  = floatval($line['quantity']);
            $cost = floatval($line['unit_cost']);
            $po->lines()->create([
                'ingredient_id'     => $line['ingredient_id'],
                'quantity'          => $qty,
                'uom_id'            => $line['uom_id'],
                'unit_cost'         => $cost,
                'total_cost'        => round($qty * $cost, 4),
                'received_quantity' => 0,
            ]);
        }

        if ($action === 'submit') {
            $msg = $requiresApproval ? 'PO submitted for approval.' : 'PO approved and sent to purchasing team.';
        } else {
            $msg = 'Purchase order saved as draft.';
        }
        session()->flash('success', $msg);
        $this->redirectRoute('purchasing.index');
    }

    public function render()
    {
        $suppliers  = Supplier::where('is_active', true)->orderBy('name')->get();
        $uoms       = UnitOfMeasure::orderBy('name')->get();
        $costCenters = IngredientCategory::roots()->active()->ordered()->get();

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $q = Ingredient::with(['baseUom'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8);

            // Prioritise ingredients linked to the selected supplier
            if ($this->supplier_id) {
                $supplierIngIds = DB::table('supplier_ingredients')
                    ->where('supplier_id', $this->supplier_id)
                    ->pluck('ingredient_id')
                    ->toArray();
                if ($supplierIngIds) {
                    $ids = implode(',', $supplierIngIds);
                    $q->orderByRaw("CASE WHEN id IN ({$ids}) THEN 0 ELSE 1 END");
                }
            }

            $searchResults = $q->get();
        }

        $grandTotal         = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_cost']));
        $availableTemplates = FormTemplate::ofType('purchase_order')->active()->ordered()->get();
        $isEditable         = ! $this->orderId || in_array($this->status, ['draft', 'submitted']);
        $requirePoApproval  = Auth::user()->company?->require_po_approval ?? true;

        $pageTitle = $this->orderId
            ? ($isEditable ? 'Edit PO: ' : 'View PO: ') . $this->poNumber
            : 'New Purchase Order';

        return view('livewire.purchasing.order-form', compact(
            'suppliers', 'uoms', 'costCenters', 'searchResults', 'grandTotal', 'availableTemplates', 'isEditable', 'requirePoApproval'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function lookupUnitCost(int $ingredientId, ?int $supplierId): float
    {
        if ($supplierId) {
            $pivot = DB::table('supplier_ingredients')
                ->where('supplier_id', $supplierId)
                ->where('ingredient_id', $ingredientId)
                ->first();
            if ($pivot && floatval($pivot->last_cost) > 0) {
                return floatval($pivot->last_cost);
            }
        }
        return floatval(Ingredient::where('id', $ingredientId)->value('purchase_price') ?? 0);
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;
        $qty  = floatval($this->lines[$idx]['quantity'] ?? 0);
        $cost = floatval($this->lines[$idx]['unit_cost'] ?? 0);
        $this->lines[$idx]['total_cost'] = round($qty * $cost, 4);
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $last   = PurchaseOrder::where('po_number', 'like', $prefix . '%')
            ->orderByDesc('po_number')
            ->value('po_number');
        $seq    = $last ? ((int) substr($last, -3) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
