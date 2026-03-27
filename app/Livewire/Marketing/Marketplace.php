<?php

namespace App\Livewire\Marketing;

use App\Models\SupplierProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Marketplace extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $stateFilter = '';
    public string $sortBy = 'relevance'; // relevance, price_low, price_high

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStateFilter(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }

    public function render()
    {
        if (Auth::check()) {
            return redirect()->route('purchasing.suppliers.directory');
        }

        $query = SupplierProduct::query()
            ->where('is_active', true)
            ->whereHas('supplier', fn ($q) => $q->where('portal_enabled', true)->where('is_active', true))
            ->with(['uom']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%')
                  ->orWhere('sku', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->stateFilter) {
            $query->whereHas('supplier', fn ($q) => $q->where('state', $this->stateFilter));
        }

        // Sort
        $query = match ($this->sortBy) {
            'price_low'  => $query->orderBy('unit_price', 'asc'),
            'price_high' => $query->orderBy('unit_price', 'desc'),
            default      => $this->search ? $query->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", [$this->search . '%']) : $query->orderBy('name'),
        };

        $products = $query->paginate(24);

        // For each product, get supplier count and price range for same product name
        $productStats = [];
        foreach ($products as $p) {
            $stats = SupplierProduct::where('name', $p->name)
                ->where('is_active', true)
                ->whereHas('supplier', fn ($q) => $q->where('portal_enabled', true)->where('is_active', true))
                ->selectRaw('COUNT(DISTINCT supplier_id) as supplier_count, MIN(unit_price) as min_price, MAX(unit_price) as max_price')
                ->first();

            $productStats[$p->id] = [
                'supplier_count' => $stats->supplier_count ?? 1,
                'min_price'      => floatval($stats->min_price ?? $p->unit_price),
                'max_price'      => floatval($stats->max_price ?? $p->unit_price),
            ];
        }

        // Filter options
        $categories = SupplierProduct::where('is_active', true)
            ->whereHas('supplier', fn ($q) => $q->where('portal_enabled', true)->where('is_active', true))
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category');

        $states = DB::table('suppliers')
            ->where('portal_enabled', true)->where('is_active', true)
            ->whereNotNull('state')->where('state', '!=', '')
            ->distinct()->orderBy('state')->pluck('state');

        // Platform stats
        $totalProducts = SupplierProduct::where('is_active', true)->count();
        $totalSuppliers = DB::table('suppliers')->where('portal_enabled', true)->where('is_active', true)->count();
        $totalCategories = SupplierProduct::where('is_active', true)->whereNotNull('category')
            ->where('category', '!=', '')->distinct()->count('category');

        return view('livewire.marketing.marketplace', compact(
            'products', 'productStats', 'categories', 'states',
            'totalProducts', 'totalSuppliers', 'totalCategories'
        ))->layout('layouts.marketing');
    }
}
