<?php

namespace App\Livewire\Purchasing;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\QuotationRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Services\RfqService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RfqForm extends Component
{
    use ScopesToActiveOutlet;

    public ?int $rfqId = null;

    // Header
    public string $rfqNumber = '';
    public string $status    = 'draft';
    public string $title     = '';
    public string $needed_by_date = '';
    public string $notes     = '';

    // Lines: [ingredient_id, ingredient_name, quantity, uom_id]
    public array  $lines            = [];
    public string $ingredientSearch = '';

    // Supplier selection (array of supplier IDs)
    public array $selectedSuppliers = [];

    protected function rules(): array
    {
        return [
            'title'                 => 'required|string|max:255',
            'needed_by_date'        => 'required|date|after_or_equal:today',
            'notes'                 => 'nullable|string',
            'lines'                 => 'required|array|min:1',
            'lines.*.ingredient_id' => 'required|exists:ingredients,id',
            'lines.*.quantity'      => 'required|numeric|min:0.0001',
            'lines.*.uom_id'       => 'required|exists:units_of_measure,id',
            'selectedSuppliers'     => 'required|array|min:1',
            'selectedSuppliers.*'   => 'exists:suppliers,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required'              => 'Please enter a title for this RFQ.',
            'needed_by_date.required'     => 'Please specify when you need the items.',
            'lines.required'              => 'Add at least one ingredient.',
            'lines.min'                   => 'Add at least one ingredient.',
            'lines.*.quantity.min'        => 'Quantity must be greater than zero.',
            'selectedSuppliers.required'  => 'Select at least one supplier.',
            'selectedSuppliers.min'       => 'Select at least one supplier.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->needed_by_date = now()->addDays(7)->toDateString();

        if (! $id) {
            $this->rfqNumber = QuotationRequest::generateNumber();
            return;
        }

        $rfq = QuotationRequest::with(['lines.ingredient.baseUom', 'lines.uom', 'suppliers'])
            ->findOrFail($id);

        $this->rfqId          = $rfq->id;
        $this->rfqNumber      = $rfq->rfq_number;
        $this->status         = $rfq->status;
        $this->title          = $rfq->title ?? '';
        $this->needed_by_date = $rfq->needed_by_date?->toDateString() ?? '';
        $this->notes          = $rfq->notes ?? '';

        $this->lines = $rfq->lines->map(fn ($l) => [
            'ingredient_id'   => $l->ingredient_id,
            'ingredient_name' => $l->ingredient?->name ?? '—',
            'quantity'        => (string) floatval($l->quantity),
            'uom_id'          => $l->uom_id,
        ])->toArray();

        $this->selectedSuppliers = $rfq->suppliers->pluck('supplier_id')->map(fn ($id) => (int) $id)->toArray();
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

        $this->lines[] = [
            'ingredient_id'   => $ingredientId,
            'ingredient_name' => $ingredient->name,
            'quantity'        => '1',
            'uom_id'          => $ingredient->base_uom_id,
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function save(string $action = 'draft'): void
    {
        $this->validate();

        $user     = Auth::user();
        $outletId = $user->activeOutletId() ?? Outlet::where('company_id', $user->company_id)->value('id');

        $data = [
            'title'          => $this->title,
            'needed_by_date' => $this->needed_by_date,
            'notes'          => $this->notes ?: null,
            'status'         => $action === 'send' ? 'sent' : 'draft',
        ];

        if ($this->rfqId) {
            $rfq = QuotationRequest::findOrFail($this->rfqId);
            $rfq->update($data);
        } else {
            $data['company_id']  = $user->company_id;
            $data['outlet_id']   = $outletId;
            $data['rfq_number']  = $this->rfqNumber;
            $data['created_by']  = Auth::id();
            $rfq = QuotationRequest::create($data);
            $this->rfqId = $rfq->id;
        }

        // Sync lines
        $rfq->lines()->delete();
        foreach ($this->lines as $line) {
            $rfq->lines()->create([
                'ingredient_id' => $line['ingredient_id'],
                'quantity'      => floatval($line['quantity']),
                'uom_id'        => $line['uom_id'],
            ]);
        }

        // Sync suppliers
        $rfq->suppliers()->delete();
        foreach ($this->selectedSuppliers as $supplierId) {
            $rfq->suppliers()->create([
                'supplier_id' => (int) $supplierId,
                'status'      => 'draft',
            ]);
        }

        // Send if requested
        if ($action === 'send') {
            RfqService::send($rfq);
            session()->flash('success', 'RFQ sent to ' . count($this->selectedSuppliers) . ' supplier(s).');
        } else {
            session()->flash('success', 'RFQ saved as draft.');
        }

        $this->redirectRoute('purchasing.rfq.index');
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $uoms      = UnitOfMeasure::orderBy('name')->get();

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

        $isEditable = ! $this->rfqId || in_array($this->status, ['draft']);

        $pageTitle = $this->rfqId
            ? ($isEditable ? 'Edit RFQ: ' : 'View RFQ: ') . $this->rfqNumber
            : 'New RFQ';

        return view('livewire.purchasing.rfq-form', compact(
            'suppliers', 'uoms', 'searchResults', 'isEditable'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }
}
