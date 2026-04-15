<?php

namespace App\Livewire\Lms;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public string $search = '';
    public string $categoryFilter = '';

    public function render()
    {
        $user = Auth::guard('lms')->user();

        $outletScope = fn ($q) => $user->outlet_id
            ? $q->where(function ($q) use ($user) {
                $q->whereDoesntHave('outlets')
                  ->orWhereHas('outlets', fn ($o) => $o->where('outlets.id', $user->outlet_id));
            })
            : $q;

        $recipes = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->tap($outletScope)
            ->with(['images', 'steps'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('is_prep')
            ->orderBy('category')
            ->orderBy('menu_sort_order')
            ->orderBy('name')
            ->get();

        $categories = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('is_prep', false)
            ->where('exclude_from_lms', false)
            ->tap($outletScope)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        $grouped = $recipes->groupBy(fn ($r) => $r->is_prep
            ? 'Prep Items'
            : ($r->category ?? 'Uncategorised'));

        return view('livewire.lms.dashboard', compact('recipes', 'categories', 'grouped'))
            ->layout('layouts.lms', ['title' => 'Training SOPs']);
    }
}
