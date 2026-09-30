<?php

namespace App\Livewire\Settings;

use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping as MappingModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierProductMapping extends Component
{
    use WithPagination;

    public ?int $supplierId = null;
    public string $search = '';

    public function updatedSupplierId(): void
    {
        // supplierId is client-writable; one this company cannot see reads as none.
        if ($this->supplierId && ! $this->visibleSupplierIds()->contains($this->supplierId)) {
            $this->supplierId = null;
        }
        $this->resetPage();
    }

    /** Portal suppliers of the active company (Supplier is company scoped). */
    private function visibleSupplierIds()
    {
        return Supplier::where('portal_enabled', true)->pluck('id')->map(fn ($i) => (int) $i);
    }
    public function updatedSearch(): void { $this->resetPage(); }

    public function mapProduct(int $supplierProductId, int $ingredientId): void
    {
        // Both ids come from the browser. The ingredient must be this company's
        // (Ingredient is company scoped); the product must come from a supplier this
        // company can see — SupplierProduct itself carries no company scope.
        abort_unless(Ingredient::whereKey($ingredientId)->exists(), 404);
        abort_unless(
            SupplierProduct::whereKey($supplierProductId)
                ->whereIn('supplier_id', $this->visibleSupplierIds()->all())
                ->exists(),
            404
        );

        MappingModel::updateOrCreate(
            [
                'company_id'          => Auth::user()->company_id,
                'supplier_product_id' => $supplierProductId,
                'ingredient_id'       => $ingredientId,
            ],
            [
                'is_verified' => true,
                'mapped_by'   => Auth::id(),
            ]
        );
        session()->flash('success', 'Supplier product mapped.');
    }

    public function removeMapping(int $id): void
    {
        MappingModel::findOrFail($id)->delete();
        session()->flash('success', 'Mapping removed.');
    }

    public function render()
    {
        $suppliers = Supplier::where('portal_enabled', true)->orderBy('name')->get();
        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();

        $products = collect();
        $mappings = collect();

        if ($this->supplierId && $suppliers->contains('id', $this->supplierId)) {
            $query = SupplierProduct::where('supplier_id', $this->supplierId)
                ->where('is_active', true);

            if ($this->search) {
                $query->where(fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%'));
            }

            $products = $query->orderBy('name')->paginate(20);

            $mappings = MappingModel::where('company_id', Auth::user()->company_id)
                ->whereIn('supplier_product_id', $products->pluck('id'))
                ->with('ingredient')
                ->get()
                ->keyBy('supplier_product_id');
        }

        return view('livewire.settings.supplier-product-mapping', compact(
            'suppliers', 'ingredients', 'products', 'mappings'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Supplier Product Mapping']);
    }
}
