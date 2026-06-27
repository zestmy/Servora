<?php

namespace App\Livewire\Inventory;

use App\Models\Department;
use App\Models\Outlet;
use App\Models\PurchaseCapture;
use App\Models\Supplier;
use App\Traits\PicksRecordOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PurchaseCaptureForm extends Component
{
    use PicksRecordOutlet;

    public ?int $recordId = null;

    public string $purchase_date    = '';
    public ?int   $department_id     = null;
    public string $supplier_id       = '';   // supplier id, '' (none), or 'other'
    public string $supplier_name     = '';   // manual name when supplier_id === 'other'
    public string $amount            = '0';
    public string $reference_number  = '';
    public string $notes             = '';

    protected function rules(): array
    {
        return [
            'outlet_id'        => 'required|integer',
            'purchase_date'    => 'required|date',
            'department_id'    => 'required|exists:departments,id',
            'supplier_id'      => 'nullable|string',
            'supplier_name'    => $this->supplier_id === 'other' ? 'required|string|max:255' : 'nullable|string|max:255',
            'amount'           => 'required|numeric|min:0',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ];
    }

    protected function messages(): array
    {
        return [
            'outlet_id.required'     => 'Select an outlet for this purchase.',
            'department_id.required' => 'Department is required.',
            'amount.required'        => 'Enter the total purchase value.',
            'amount.min'             => 'Amount cannot be negative.',
            'supplier_name.required' => 'Enter the supplier name.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->purchase_date = now()->toDateString();

        if (! $id) {
            $this->initOutlet();
            return;
        }

        $record = PurchaseCapture::findOrFail($id);

        $this->recordId         = $record->id;
        $this->initOutlet($record->outlet_id);
        $this->purchase_date    = $record->purchase_date->toDateString();
        $this->department_id    = $record->department_id;
        $this->amount           = (string) floatval($record->amount);
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';

        // Rehydrate the supplier selector: linked supplier → its id; manual name → 'other'.
        if ($record->supplier_id) {
            $this->supplier_id = (string) $record->supplier_id;
        } elseif ($record->supplier_name) {
            $this->supplier_id   = 'other';
            $this->supplier_name = $record->supplier_name;
        }
    }

    public function save(): void
    {
        $this->validate();

        // Resolve supplier: a real id links by FK; 'other' stores a free-text name.
        $supplierId   = null;
        $supplierName = null;
        if ($this->supplier_id === 'other') {
            $supplierName = trim($this->supplier_name) ?: null;
        } elseif ($this->supplier_id !== '') {
            $supplierId = (int) $this->supplier_id;
        }

        $data = [
            'department_id'    => $this->department_id,
            'supplier_id'      => $supplierId,
            'supplier_name'    => $supplierName,
            'purchase_date'    => $this->purchase_date,
            'amount'           => round(floatval($this->amount), 4),
            'reference_number' => $this->reference_number ?: null,
            'notes'            => $this->notes ?: null,
        ];

        if ($this->recordId) {
            PurchaseCapture::findOrFail($this->recordId)->update($data);
            session()->flash('success', 'Purchase updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $this->resolveOutletId();
            $data['created_by'] = Auth::id();
            PurchaseCapture::create($data);
            session()->flash('success', 'Purchase recorded.');
        }

        $this->redirectRoute('inventory.index', ['tab' => 'purchases']);
    }

    public function render()
    {
        $departments = Department::active()->ordered()->get();
        $suppliers   = Supplier::where('is_active', true)->orderBy('name')->get();

        $pageTitle = $this->recordId ? 'Purchase' : 'Record Purchase';

        $outletOptions      = $this->outletOptions();
        $canChooseOutlet    = ! $this->recordId && $this->hasOutletChoice();
        $selectedOutletName = Outlet::find($this->outlet_id)?->name;

        return view('livewire.inventory.purchase-capture-form', compact(
            'departments', 'suppliers', 'outletOptions', 'canChooseOutlet', 'selectedOutletName'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }
}
