<?php

namespace App\Livewire\Purchasing;

use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierDirectory extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $stateFilter = '';
    public string $cityFilter = '';

    public ?int $viewingSupplierId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStateFilter(): void { $this->resetPage(); }
    public function updatedCityFilter(): void { $this->resetPage(); }

    public function viewProducts(int $supplierId): void
    {
        $this->viewingSupplierId = $this->viewingSupplierId === $supplierId ? null : $supplierId;
    }

    /**
     * Add a supplier to the company's supplier list and optionally map a product to an ingredient.
     */
    public function addSupplier(int $supplierId): void
    {
        $companyId = Auth::user()->company_id;
        $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);

        // Check if already linked to this company
        if ($supplier->company_id === $companyId) {
            session()->flash('info', "{$supplier->name} is already in your supplier list.");
            return;
        }

        // If supplier has no company, claim them. Otherwise duplicate for this company.
        if ($supplier->company_id === null) {
            $supplier->update(['company_id' => $companyId]);
            session()->flash('success', "{$supplier->name} added to your supplier list.");
        } else {
            // Create a company-specific copy
            $newSupplier = Supplier::withoutGlobalScopes()->create([
                'company_id'              => $companyId,
                'name'                    => $supplier->name,
                'code'                    => $supplier->code,
                'contact_person'          => $supplier->contact_person,
                'email'                   => $supplier->email,
                'phone'                   => $supplier->phone,
                'address'                 => $supplier->address,
                'city'                    => $supplier->city,
                'state'                   => $supplier->state,
                'country'                 => $supplier->country,
                'payment_terms'           => $supplier->payment_terms,
                'whatsapp_number'         => $supplier->whatsapp_number,
                'notification_preference' => $supplier->notification_preference,
                'portal_enabled'          => $supplier->portal_enabled,
                'is_active'               => true,
            ]);
            session()->flash('success', "{$supplier->name} added to your supplier list.");
        }
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        // Query portal-registered suppliers with products
        $query = Supplier::withoutGlobalScopes()
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->has('products')
            ->withCount('products');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('products', fn ($p) => $p->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('sku', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->categoryFilter) {
            $query->whereHas('products', fn ($p) => $p->where('category', $this->categoryFilter));
        }

        if ($this->stateFilter) {
            $query->where('state', $this->stateFilter);
        }

        if ($this->cityFilter) {
            $query->where('city', 'like', '%' . $this->cityFilter . '%');
        }

        $suppliers = $query->orderBy('name')->paginate(12);

        // Get distinct categories and states for filters
        $categories = SupplierProduct::whereHas('supplier', fn ($q) => $q->where('portal_enabled', true)->where('is_active', true))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $states = Supplier::withoutGlobalScopes()
            ->where('portal_enabled', true)
            ->where('is_active', true)
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        // Products for the currently expanded supplier
        $viewingProducts = collect();
        if ($this->viewingSupplierId) {
            $viewingProducts = SupplierProduct::where('supplier_id', $this->viewingSupplierId)
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();
        }

        // Which suppliers are already in the company
        $mySupplierEmails = Supplier::where('company_id', $companyId)->pluck('email')->filter()->toArray();

        return view('livewire.purchasing.supplier-directory', compact(
            'suppliers', 'categories', 'states', 'viewingProducts', 'mySupplierEmails'
        ))->layout('layouts.app', ['title' => 'Find Suppliers']);
    }
}
