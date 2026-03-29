<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TransferForm extends Component
{
    public ?int $transferId = null;

    public string $transfer_date   = '';
    public string $transfer_number = '';
    public string $from_outlet_id  = '';
    public string $to_outlet_id    = '';
    public string $status          = 'draft';
    public string $notes           = '';

    public array  $lines      = [];
    public string $itemSearch = '';

    protected function rules(): array
    {
        return [
            'transfer_date'     => 'required|date',
            'from_outlet_id'    => 'required|exists:outlets,id',
            'to_outlet_id'      => 'required|exists:outlets,id|different:from_outlet_id',
            'lines'             => 'required|array|min:1',
            'lines.*.quantity'  => 'required|numeric|min:0.0001',
            'lines.*.unit_cost' => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'          => 'Add at least one item.',
            'lines.min'               => 'Add at least one item.',
            'lines.*.quantity.min'     => 'Quantity must be greater than zero.',
            'to_outlet_id.different'   => 'Destination outlet must be different from source.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->transfer_date = now()->toDateString();

        if ($id) {
            $transfer = OutletTransfer::with(['lines.ingredient.baseUom', 'lines.uom'])->findOrFail($id);

            $this->transferId      = $transfer->id;
            $this->transfer_date   = $transfer->transfer_date->toDateString();
            $this->transfer_number = $transfer->transfer_number;
            $this->from_outlet_id  = (string) $transfer->from_outlet_id;
            $this->to_outlet_id    = (string) $transfer->to_outlet_id;
            $this->status          = $transfer->status;
            $this->notes           = $transfer->notes ?? '';

            $this->lines = $transfer->lines->map(fn ($l) => [
                'ingredient_id' => $l->ingredient_id,
                'item_name'     => $l->ingredient?->name ?? '—',
                'is_prep'       => (bool) ($l->ingredient?->is_prep ?? false),
                'uom_id'        => $l->uom_id,
                'uom_abbr'      => $l->uom?->abbreviation ?? '',
                'quantity'      => (string) floatval($l->quantity),
                'unit_cost'     => (string) floatval($l->unit_cost),
                'total_cost'    => round(floatval($l->quantity) * floatval($l->unit_cost), 4),
            ])->toArray();
        } else {
            $this->transfer_number = $this->generateTransferNumber();

            $activeOutletId = Auth::user()->activeOutletId();
            if ($activeOutletId) {
                $this->from_outlet_id = (string) $activeOutletId;
            }
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->itemSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with(['baseUom'])->findOrFail($ingredientId);

        $unitCost = $ingredient->is_prep
            ? floatval($ingredient->current_cost)
            : floatval($ingredient->purchase_price);

        $this->lines[] = [
            'ingredient_id' => $ingredient->id,
            'item_name'     => $ingredient->name,
            'is_prep'       => (bool) $ingredient->is_prep,
            'uom_id'        => $ingredient->base_uom_id,
            'uom_abbr'      => $ingredient->baseUom->abbreviation ?? '',
            'quantity'      => '1',
            'unit_cost'     => (string) $unitCost,
            'total_cost'    => $unitCost,
        ];

        $this->itemSearch = '';
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

    public function save(): void
    {
        if ($this->status !== 'draft') {
            return;
        }

        $this->validate();

        $totalCost = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));

        $data = [
            'transfer_date'  => $this->transfer_date,
            'from_outlet_id' => (int) $this->from_outlet_id,
            'to_outlet_id'   => (int) $this->to_outlet_id,
            'notes'          => $this->notes ?: null,
        ];

        if ($this->transferId) {
            $transfer = OutletTransfer::findOrFail($this->transferId);
            $transfer->update($data);
            session()->flash('success', 'Transfer updated.');
        } else {
            $data['company_id']       = Auth::user()->company_id;
            $data['transfer_number']  = $this->transfer_number;
            $data['status']           = 'draft';
            $data['created_by']       = Auth::id();
            $transfer = OutletTransfer::create($data);
            $this->transferId = $transfer->id;
            session()->flash('success', 'Transfer created.');
        }

        $transfer->lines()->delete();
        foreach ($this->lines as $line) {
            $qty      = floatval($line['quantity']);
            $unitCost = floatval($line['unit_cost']);

            $transfer->lines()->create([
                'ingredient_id' => $line['ingredient_id'],
                'uom_id'        => $line['uom_id'],
                'quantity'      => $qty,
                'unit_cost'     => $unitCost,
            ]);
        }

        $this->redirectRoute('inventory.index', ['tab' => 'transfers']);
    }

    public function send(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if ($transfer->status !== 'draft') {
            return;
        }

        $transfer->update(['status' => 'in_transit']);
        $this->status = 'in_transit';
        session()->flash('success', 'Transfer sent — now in transit.');
    }

    public function receive(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if ($transfer->status !== 'in_transit') {
            return;
        }

        $transfer->update(['status' => 'received']);
        $this->status = 'received';
        session()->flash('success', 'Transfer received successfully.');
    }

    public function cancel(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if (! in_array($transfer->status, ['draft', 'in_transit'])) {
            return;
        }

        $transfer->update(['status' => 'cancelled']);
        $this->status = 'cancelled';
        session()->flash('success', 'Transfer cancelled.');
    }

    public function render()
    {
        $ingredientResults = collect();

        if (strlen($this->itemSearch) >= 2) {
            $existingIds = collect($this->lines)
                ->pluck('ingredient_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $ingredientResults = Ingredient::with(['baseUom'])
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->itemSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->itemSearch . '%');
                })
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $outlets   = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)->orderBy('name')->get();
        $totalCost = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));
        $pageTitle = $this->transferId ? 'Transfer ' . $this->transfer_number : 'New Transfer';
        $isDraft   = $this->status === 'draft';

        return view('livewire.inventory.transfer-form', compact(
            'ingredientResults', 'outlets', 'totalCost', 'isDraft'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) {
            return;
        }
        $qty      = floatval($this->lines[$idx]['quantity'] ?? 0);
        $unitCost = floatval($this->lines[$idx]['unit_cost'] ?? 0);
        $this->lines[$idx]['total_cost'] = round($qty * $unitCost, 4);
    }

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
}
