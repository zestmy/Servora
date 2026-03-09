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
        $user = Auth::user();
        $id = $outletId !== '' ? (int) $outletId : null;

        if ($id && ! $user->canAccessOutlet($id)) {
            return;
        }

        if ($id === null && ! $user->canViewAllOutlets()) {
            return;
        }

        session(['active_outlet_id' => $id]);
        $this->activeOutletId = $outletId;

        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        $user = Auth::user();

        if ($user->hasRole('Purchasing')) {
            return view('livewire.outlet-switcher', [
                'outlets'    => collect(),
                'canViewAll' => false,
                'hidden'     => true,
            ]);
        }

        $outlets = $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get()
            : $user->outlets()->where('is_active', true)->orderBy('name')->get();

        $canViewAll = $user->canViewAllOutlets();

        return view('livewire.outlet-switcher', compact('outlets', 'canViewAll') + ['hidden' => false]);
    }
}
