<?php

namespace App\Livewire\Purchasing;

use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\GoodsReceivedNote;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\ProcurementInvoice;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CreditNoteForm extends Component
{
    public ?int $creditNoteId = null;

    // Header
    public string $credit_note_number = '';
    public string $type               = 'debit_note';
    public string $direction          = 'issued';
    public string $status             = 'draft';
    public ?int   $supplier_id        = null;
    public ?int   $outlet_id          = null;
    public string $issued_date        = '';
    public ?int   $procurement_invoice_id  = null;
    public ?int   $goods_received_note_id  = null;
    public string $reason             = '';
    public string $notes              = '';

    // Lines
    public array  $lines            = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'type'                     => 'required|in:debit_note,credit_note',
            'direction'                => 'required|in:issued,received',
            'supplier_id'              => 'required|exists:suppliers,id',
            'issued_date'              => 'required|date',
            'procurement_invoice_id'   => 'nullable|exists:procurement_invoices,id',
            'goods_received_note_id'   => 'nullable|exists:goods_received_notes,id',
            'reason'                   => 'nullable|string|max:500',
            'notes'                    => 'nullable|string',
            'lines'                    => 'required|array|min:1',
            'lines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'lines.*.quantity'         => 'required|numeric|min:0.0001',
            'lines.*.unit_price'       => 'required|numeric|min:0',
            'lines.*.reason_code'      => 'required|in:damaged,rejected,short_delivery,return,overcharge,other',
        ];
    }

    protected function messages(): array
    {
        return [
            'supplier_id.required'          => 'Please select a supplier.',
            'lines.required'                => 'Add at least one line item.',
            'lines.min'                     => 'Add at least one line item.',
            'lines.*.quantity.min'          => 'Quantity must be greater than zero.',
            'lines.*.unit_price.required'   => 'Unit price is required.',
            'lines.*.reason_code.required'  => 'Please select a reason code.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->issued_date = now()->toDateString();

        $user = Auth::user();
        $this->outlet_id = $user->activeOutletId()
            ?? Outlet::where('company_id', $user->company_id)->value('id');

        if (! $id) {
            $this->credit_note_number = CreditNote::generateNumber($this->type);
            return;
        }

        $cn = CreditNote::with(['lines.ingredient.baseUom', 'lines.uom'])->findOrFail($id);

        $this->creditNoteId            = $cn->id;
        $this->credit_note_number      = $cn->credit_note_number;
        $this->type                    = $cn->type;
        $this->direction               = $cn->direction;
        $this->status                  = $cn->status;
        $this->supplier_id             = $cn->supplier_id;
        $this->outlet_id               = $cn->outlet_id;
        $this->issued_date             = $cn->issued_date->toDateString();
        $this->procurement_invoice_id  = $cn->procurement_invoice_id;
        $this->goods_received_note_id  = $cn->goods_received_note_id;
        $this->reason                  = $cn->reason ?? '';
        $this->notes                   = $cn->notes ?? '';

        $this->lines = $cn->lines->map(fn ($l) => [
            'ingredient_id'   => $l->ingredient_id,
            'ingredient_name' => $l->ingredient?->name ?? '—',
            'description'     => $l->description ?? '',
            'quantity'        => (string) floatval($l->quantity),
            'uom_id'          => $l->uom_id,
            'unit_price'      => (string) floatval($l->unit_price),
            'total_price'     => round(floatval($l->quantity) * floatval($l->unit_price), 4),
            'reason_code'     => $l->reason_code ?? 'other',
        ])->toArray();
    }

    public function updatedType(): void
    {
        if (! $this->creditNoteId) {
            $this->credit_note_number = CreditNote::generateNumber($this->type);
        }
    }

    public function updatedGoodsReceivedNoteId(): void
    {
        if (! $this->goods_received_note_id) return;

        $grn = GoodsReceivedNote::with(['lines.ingredient.baseUom', 'lines.uom'])
            ->find($this->goods_received_note_id);

        if (! $grn) return;

        // Auto-fill supplier if not set
        if (! $this->supplier_id && $grn->supplier_id) {
            $this->supplier_id = $grn->supplier_id;
        }

        // Populate lines from GRN variance
        $this->lines = [];

        foreach ($grn->lines as $line) {
            $expected = floatval($line->expected_quantity);
            $received = floatval($line->received_quantity);
            $unitCost = floatval($line->unit_cost);

            if ($line->condition === 'damaged') {
                $this->lines[] = [
                    'ingredient_id'   => $line->ingredient_id,
                    'ingredient_name' => $line->ingredient?->name ?? '—',
                    'description'     => ($line->ingredient?->name ?? '') . ' — Damaged',
                    'quantity'        => (string) $received,
                    'uom_id'          => $line->uom_id,
                    'unit_price'      => (string) $unitCost,
                    'total_price'     => round($received * $unitCost, 4),
                    'reason_code'     => 'damaged',
                ];
            } elseif ($line->condition === 'rejected') {
                $qty = $received > 0 ? $received : $expected;
                $this->lines[] = [
                    'ingredient_id'   => $line->ingredient_id,
                    'ingredient_name' => $line->ingredient?->name ?? '—',
                    'description'     => ($line->ingredient?->name ?? '') . ' — Rejected',
                    'quantity'        => (string) $qty,
                    'uom_id'          => $line->uom_id,
                    'unit_price'      => (string) $unitCost,
                    'total_price'     => round($qty * $unitCost, 4),
                    'reason_code'     => 'rejected',
                ];
            } elseif ($received < $expected && $line->condition === 'good') {
                $shortQty = $expected - $received;
                $this->lines[] = [
                    'ingredient_id'   => $line->ingredient_id,
                    'ingredient_name' => $line->ingredient?->name ?? '—',
                    'description'     => ($line->ingredient?->name ?? '') . ' — Short delivery',
                    'quantity'        => (string) $shortQty,
                    'uom_id'          => $line->uom_id,
                    'unit_price'      => (string) $unitCost,
                    'total_price'     => round($shortQty * $unitCost, 4),
                    'reason_code'     => 'short_delivery',
                ];
            }
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $unitPrice = floatval($ingredient->purchase_price ?? 0);

        $this->lines[] = [
            'ingredient_id'   => $ingredientId,
            'ingredient_name' => $ingredient->name,
            'description'     => '',
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
            'unit_price'      => (string) $unitPrice,
            'total_price'     => $unitPrice,
            'reason_code'     => 'other',
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
        if (count($parts) !== 2) return;

        $idx   = (int) $parts[0];
        $field = $parts[1];

        if (in_array($field, ['quantity', 'unit_price'])) {
            $this->recalcLine($idx);
        }
    }

    public function save(string $action = 'save'): void
    {
        $this->validate();

        $user    = Auth::user();
        $company = $user->company;
        $taxPct  = floatval($company?->tax_percent ?? 0);

        // Remove zero-quantity lines
        $this->lines = array_values(array_filter($this->lines, fn ($l) => floatval($l['quantity']) > 0));

        $subtotal = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_price']));
        $taxAmt   = $taxPct > 0 ? round($subtotal * ($taxPct / 100), 4) : 0;
        $total    = round($subtotal + $taxAmt, 4);

        $status = $action === 'issue' ? 'issued' : ($this->status ?: 'draft');

        $data = [
            'type'                     => $this->type,
            'direction'                => $this->direction,
            'supplier_id'              => $this->supplier_id,
            'outlet_id'                => $this->outlet_id,
            'issued_date'              => $this->issued_date,
            'procurement_invoice_id'   => $this->procurement_invoice_id ?: null,
            'goods_received_note_id'   => $this->goods_received_note_id ?: null,
            'subtotal'                 => round($subtotal, 4),
            'tax_amount'               => $taxAmt,
            'total_amount'             => $total,
            'reason'                   => $this->reason ?: null,
            'notes'                    => $this->notes ?: null,
            'status'                   => $status,
        ];

        if ($this->creditNoteId) {
            $cn = CreditNote::findOrFail($this->creditNoteId);
            $cn->update($data);
        } else {
            $data['company_id']          = $user->company_id;
            $data['credit_note_number']  = $this->credit_note_number;
            $data['created_by']          = Auth::id();
            $cn = CreditNote::create($data);
        }

        // Sync lines
        $cn->lines()->delete();
        foreach ($this->lines as $line) {
            $qty   = floatval($line['quantity']);
            $price = floatval($line['unit_price']);
            $cn->lines()->create([
                'ingredient_id' => $line['ingredient_id'],
                'description'   => $line['description'] ?? null,
                'quantity'      => $qty,
                'uom_id'        => $line['uom_id'],
                'unit_price'    => $price,
                'total_price'   => round($qty * $price, 4),
                'reason_code'   => $line['reason_code'],
            ]);
        }

        $msg = $action === 'issue'
            ? "Note {$cn->credit_note_number} saved and issued."
            : "Note {$cn->credit_note_number} saved as draft.";
        session()->flash('success', $msg);
        $this->redirectRoute('purchasing.credit-notes.index');
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $uoms      = UnitOfMeasure::orderBy('name')->get();

        $invoices = collect();
        if ($this->supplier_id) {
            $invoices = ProcurementInvoice::where('supplier_id', $this->supplier_id)
                ->whereIn('status', ['issued', 'overdue', 'paid'])
                ->orderByDesc('issued_date')
                ->get(['id', 'invoice_number', 'total_amount']);
        }

        $grns = collect();
        if ($this->supplier_id) {
            $grns = GoodsReceivedNote::where('supplier_id', $this->supplier_id)
                ->orderByDesc('received_date')
                ->get(['id', 'grn_number', 'received_date']);
        }

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $q = Ingredient::with('baseUom')
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8);

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

        $subtotal   = collect($this->lines)->sum(fn ($l) => floatval($l['quantity']) * floatval($l['unit_price']));
        $company    = Auth::user()->company;
        $taxPct     = floatval($company?->tax_percent ?? 0);
        $taxAmount  = $taxPct > 0 ? round($subtotal * ($taxPct / 100), 4) : 0;
        $grandTotal = round($subtotal + $taxAmount, 4);
        $isEditable = ! $this->creditNoteId || in_array($this->status, ['draft']);

        $pageTitle = $this->creditNoteId
            ? ($isEditable ? 'Edit: ' : 'View: ') . $this->credit_note_number
            : 'New Credit/Debit Note';

        return view('livewire.purchasing.credit-note-form', compact(
            'suppliers', 'uoms', 'invoices', 'grns', 'searchResults',
            'subtotal', 'taxPct', 'taxAmount', 'grandTotal', 'isEditable'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;
        $qty   = floatval($this->lines[$idx]['quantity'] ?? 0);
        $price = floatval($this->lines[$idx]['unit_price'] ?? 0);
        $this->lines[$idx]['total_price'] = round($qty * $price, 4);
    }
}
