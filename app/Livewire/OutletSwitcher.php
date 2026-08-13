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

        if ($user->can('purchasing.view') && ! $user->can('sales.view')) {
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

        /*
         * The two lists PARTITION what the user can reach — every outlet in
         * $allOutlets lands in exactly one of them.
         *
         * The kitchen half used to be gated on isKitchenUser(), while the
         * ordinary half rejected kitchen outlets regardless. Somebody holding
         * a kitchen's base outlet without the kitchen_users row therefore had
         * it in neither: an outlet they had been granted and could not switch
         * to. The same shape as the user access screen, where a stood-down
         * kitchen's outlet fell between the outlet checkboxes and the kitchen
         * block.
         *
         * The gate is unnecessary as well as harmful: $allOutlets is already
         * only what this user can reach, so a non-kitchen user simply has none
         * and the section hides itself.
         */
        $outlets        = $allOutlets->reject(fn ($o) => in_array($o->id, $kitchenOutletIds));
        $kitchenOutlets = $allOutlets->filter(fn ($o) => in_array($o->id, $kitchenOutletIds));
        $canViewAll = $user->canViewAllOutlets();

        return view('livewire.outlet-switcher', compact('outlets', 'kitchenOutlets', 'canViewAll') + ['hidden' => false]);
    }
}
