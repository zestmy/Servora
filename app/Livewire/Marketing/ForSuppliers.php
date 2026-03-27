<?php

namespace App\Livewire\Marketing;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ForSuppliers extends Component
{
    public function render()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $stats = [
            'companies'   => Company::where('is_active', true)->count(),
            'ingredients' => Ingredient::withoutGlobalScopes()->count(),
            'orders'      => PurchaseOrder::withoutGlobalScopes()->count(),
        ];

        return view('livewire.marketing.for-suppliers', compact('stats'))
            ->layout('layouts.marketing');
    }
}
