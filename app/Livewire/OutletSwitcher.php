<?php

namespace App\Livewire;

use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OutletSwitcher extends Component
{
    public string $activeOutletId = '';

    public function mount(): void
    {
        $this->activeOutletId = (string) (Auth::user()->activeOutletId() ?? '');
    }

    public function switchOutlet(string $outletId): void
    {
        // Outlet switcher removed — no session write. Listings now scope to
        // all outlets the user can access (ScopesToActiveOutlet::availableOutletIds).
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        $user = Auth::user();

        if ($user->hasPermissionTo('purchasing.view') && ! $user->hasPermissionTo('sales.view')) {
            return view('livewire.outlet-switcher', [
                'outlets'    => collect(),
                'canViewAll' => false,
                'hidden'     => true,
            ]);
        }

        $allOutlets = $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get()
            : $user->outlets()->where('outlets.company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get();

        $kitchenOutletIds = \App\Models\CentralKitchen::where('company_id', $user->company_id)
            ->whereNotNull('outlet_id')->pluck('outlet_id')->toArray();

        $outlets = $allOutlets->reject(fn ($o) => in_array($o->id, $kitchenOutletIds));
        $kitchenOutlets = $user->isKitchenUser()
            ? $allOutlets->filter(fn ($o) => in_array($o->id, $kitchenOutletIds))
            : collect();
        $canViewAll = $user->canViewAllOutlets();

        return view('livewire.outlet-switcher', compact('outlets', 'kitchenOutlets', 'canViewAll') + ['hidden' => false]);
    }
}
