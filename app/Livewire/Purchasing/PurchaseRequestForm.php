<?php

namespace App\Livewire\Purchasing;

use App\Models\Department;
use App\Models\Ingredient;
use App\Models\IngredientParLevel;
use App\Models\Outlet;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Services\PurchaseRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PurchaseRequestForm extends Component
{
    public ?int $requestId = null;

    public string $prNumber = '';
    public string $status   = 'draft';

    public string $requested_date = '';
    public string $needed_by_date = '';
    public string $notes          = '';
    public ?int   $department_id  = null;

    public array  $lines            = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'requested_date'                  => 'required|date',
            'needed_by_date'                  => 'nullable|date|after_or_equal:requested_date',
            'notes'                           => 'nullable|string',
            'department_id'                   => 'nullable|exists:departments,id',
            'lines'                           => 'required|array|min:1',
            'lines.*.ingredient_id'           => 'nullable|exists:ingredients,id',
            'lines.*.custom_name'             => 'nullable|string|max:200',
            'lines.*.quantity'                => 'required|numeric|min:0.0001',
            'lines.*.uom_id'                 => 'required|exists:units_of_measure,id',
            'lines.*.preferred_supplier_id'   => 'nullable|exists:suppliers,id',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'           => 'Add at least one ingredient.',
            'lines.min'                => 'Add at least one ingredient.',
            'lines.*.quantity.min'     => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->requested_date = now()->toDateString();

        if (! $id) {
            $this->prNumber = PurchaseRequestService::generatePrNumber();
            return;
        }

        $pr = PurchaseRequest::with(['lines.ingredient.baseUom', 'lines.uom', 'lines.preferredSupplier'])->findOrFail($id);

        $this->requestId      = $pr->id;
        $this->prNumber       = $pr->pr_number;
        $this->status         = $pr->status;
        $this->requested_date = $pr->requested_date->toDateString();
        $this->needed_by_date = $pr->needed_by_date?->toDateString() ?? '';
        $this->notes          = $pr->notes ?? '';
        $this->department_id  = $pr->department_id;

        foreach ($pr->lines as $line) {
            $this->lines[] = [
                'ingredient_id'        => $line->ingredient_id,
                'ingredient_name'      => $line->ingredient?->name ?? $line->custom_name ?? '—',
                'custom_name'          => $line->custom_name,
                'quantity'             => (float) $line->quantity,
                'uom_id'              => $line->uom_id,
                'preferred_supplier_id' => $line->preferred_supplier_id,
                'supplier_name'        => $line->preferredSupplier?->name ?? '',
                'par_level'            => $line->ingredient_id ? $this->getParLevel($line->ingredient_id) : 0,
                'notes'                => $line->notes ?? '',
            ];
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        // Prevent duplicate
        foreach ($this->lines as $line) {
            if ($line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
        if (! $ingredient) return;

        // Find preferred supplier
        $preferred = $ingredient->supplierIngredients()
            ->where('is_preferred', true)
            ->with('supplier')
            ->first();

        $this->lines[] = [
            'ingredient_id'        => $ingredient->id,
            'ingredient_name'      => $ingredient->name,
            'quantity'             => 0,
            'uom_id'              => $ingredient->base_uom_id,
            'preferred_supplier_id' => $preferred?->supplier_id,
            'supplier_name'        => $preferred?->supplier?->name ?? '',
            'par_level'            => $this->getParLevel($ingredient->id),
            'notes'                => '',
        ];

        $this->ingredientSearch = '';
    }

    public string $customItemName = '';

    public function addCustomItem(): void
    {
        if (strlen(trim($this->customItemName)) < 2) return;

        $defaultUom = \App\Models\UnitOfMeasure::first();

        $this->lines[] = [
            'ingredient_id'        => null,
            'ingredient_name'      => strtoupper(trim($this->customItemName)),
            'custom_name'          => strtoupper(trim($this->customItemName)),
            'quantity'             => 1,
            'uom_id'              => $defaultUom?->id,
            'preferred_supplier_id' => null,
            'supplier_name'        => '',
            'par_level'            => 0,
            'notes'                => '',
        ];

        $this->customItemName = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(string $action = 'save')
    {
        $this->validate();

        $user = Auth::user();
        $company = $user->company;
        $outletId = $user->activeOutletId() ?: $user->outlets()->first()?->id;

        // Determine the CPU (first active one for this company)
        $cpu = $company->cpus()->where('is_active', true)->first();

        $data = [
            'company_id'     => $user->company_id,
            'outlet_id'      => $outletId,
            'cpu_id'         => $cpu?->id,
            'pr_number'      => $this->prNumber,
            'requested_date' => $this->requested_date,
            'needed_by_date' => $this->needed_by_date ?: null,
            'notes'          => $this->notes ?: null,
            'department_id'  => $this->department_id,
            'created_by'     => $user->id,
        ];

        if ($action === 'submit') {
            $requiresApproval = $company->require_pr_approval ?? false;
            $data['status'] = $requiresApproval ? 'submitted' : 'approved';
            if (! $requiresApproval) {
                $data['approved_by'] = $user->id;
                $data['approved_at'] = now();
            }
        } else {
            $data['status'] = 'draft';
        }

        if ($this->requestId) {
            $pr = PurchaseRequest::findOrFail($this->requestId);
            $pr->update($data);
            $pr->lines()->delete();
        } else {
            $pr = PurchaseRequest::create($data);
            $this->requestId = $pr->id;
        }

        foreach ($this->lines as $line) {
            $pr->lines()->create([
                'ingredient_id'        => $line['ingredient_id'] ?: null,
                'custom_name'          => $line['custom_name'] ?? null,
                'quantity'             => $line['quantity'],
                'uom_id'              => $line['uom_id'],
                'preferred_supplier_id' => $line['preferred_supplier_id'] ?: null,
                'notes'                => $line['notes'] ?? null,
            ]);
        }

        $this->status = $pr->status;

        $message = $action === 'submit'
            ? ($data['status'] === 'submitted' ? 'Purchase request submitted for approval.' : 'Purchase request approved.')
            : 'Purchase request saved as draft.';

        session()->flash('success', $message);

        return $this->redirect(route('purchasing.index', ['tab' => 'pr']), navigate: true);
    }

    private function getParLevel(int $ingredientId): float
    {
        $outletId = Auth::user()->activeOutletId();
        if (! $outletId) return 0;

        return (float) (IngredientParLevel::where('ingredient_id', $ingredientId)
            ->where('outlet_id', $outletId)
            ->value('par_level') ?? 0);
    }

    public function render()
    {
        $isEditable = in_array($this->status, ['draft', '']);

        $searchResults = [];
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->with('baseUom')
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('sort_order')->get();
        $uoms = UnitOfMeasure::orderBy('name')->get();

        return view('livewire.purchasing.purchase-request-form', [
            'searchResults' => $searchResults,
            'suppliers'     => $suppliers,
            'departments'   => $departments,
            'uoms'          => $uoms,
            'isEditable'    => $isEditable,
        ])->layout('layouts.app', ['title' => $this->requestId ? 'Edit Purchase Request' : 'New Purchase Request']);
    }
}
