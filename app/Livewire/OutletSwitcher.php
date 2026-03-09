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

    public function updatedActiveOutletId(): void
    {
        $user = Auth::user();
        $id = $this->activeOutletId !== '' ? (int) $this->activeOutletId : null;

        // Validate access
        if ($id && ! $user->canAccessOutlet($id)) {
            // Revert to session value
            $this->activeOutletId = (string) (session('active_outlet_id') ?? '');
            return;
        }

        // "All Outlets" only for Business Manager / Super Admin
        if ($id === null && ! $user->canViewAllOutlets()) {
            $this->activeOutletId = (string) (session('active_outlet_id') ?? '');
            return;
        }

        // Skip redirect if value hasn't actually changed
        $currentSessionId = session('active_outlet_id');
        if ($id === $currentSessionId || ($id === null && $currentSessionId === null)) {
            return;
        }

        session(['active_outlet_id' => $id]);

        // Full page reload to re-scope all data
        $this->redirect(request()->header('Referer', route('dashboard')));
    }

    public function render()
    {
        $user = Auth::user();

        // Purchasing role sees all outlets centrally — no switcher needed
        if ($user->hasRole('Purchasing')) {
            return view('livewire.outlet-switcher', [
                'outlets'      => collect(),
                'canViewAll'   => false,
                'activeOutlet' => null,
                'hidden'       => true,
            ]);
        }

        $outlets = $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->where('is_active', true)->orderBy('name')->get()
            : $user->outlets()->where('is_active', true)->orderBy('name')->get();

        $canViewAll = $user->canViewAllOutlets();
        $activeOutlet = $this->activeOutletId
            ? Outlet::find($this->activeOutletId)
            : null;

        return view('livewire.outlet-switcher', compact('outlets', 'canViewAll', 'activeOutlet') + ['hidden' => false]);
    }
}
