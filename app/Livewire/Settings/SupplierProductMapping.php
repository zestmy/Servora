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

    public function updatedSupplierId(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function mapProduct(int $supplierProductId, int $ingredientId): void
    {
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
        session()->flash('success', 'Product mapped to ingredient.');
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

        if ($this->supplierId) {
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
        ))->layout('layouts.app', ['title' => 'Supplier Product Mapping']);
    }
}
