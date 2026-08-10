<?php

namespace App\Livewire\Settings;

use App\Models\CentralKitchen;
use App\Models\CentralPurchasingUnit;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Outlets extends Component
{
    public bool   $showModal = false;
    public ?int   $editingId = null;

    public string $name    = '';
    public string $code    = '';
    public string $phone   = '';
    public string $address = '';
    public string $country = '';
    public string $state   = '';
    public bool   $is_active = true;
    public ?int   $default_kitchen_id = null;
    public ?int   $default_cpu_id     = null;

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);

        $this->editingId = $outlet->id;
        $this->name      = $outlet->name;
        $this->code      = $outlet->code ?? '';
        $this->phone     = $outlet->phone ?? '';
        $this->address   = $outlet->address ?? '';
        $this->country   = $outlet->country ?? '';
        $this->state     = $outlet->state ?? '';
        $this->is_active = $outlet->is_active;
        $this->default_kitchen_id = $outlet->default_kitchen_id;
        $this->default_cpu_id     = $outlet->default_cpu_id;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'name'    => 'required|string|max:100',
            'code'    => ['required', 'string', 'max:20', Rule::unique('outlets', 'code')->where('company_id', $companyId)->ignore($this->editingId)],
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'country' => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'default_kitchen_id' => ['nullable', Rule::exists('central_kitchens', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'default_cpu_id'     => ['nullable', Rule::exists('central_purchasing_units', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
        ]);

        $data = [
            'company_id' => $companyId,
            'name'       => $this->name,
            'code'       => strtoupper($this->code),
            'phone'      => $this->phone ?: null,
            'address'    => $this->address ?: null,
            'country'    => $this->country ?: null,
            'state'      => $this->state ?: null,
            'is_active'  => $this->is_active,
            'default_kitchen_id' => $this->default_kitchen_id ?: null,
            'default_cpu_id'     => $this->default_cpu_id ?: null,
        ];

        if ($this->editingId) {
            $outlet = Outlet::where('company_id', $companyId)->findOrFail($this->editingId);
            $outlet->update($data);
            session()->flash('success', 'Branch updated.');
        } else {
            $outlet = Outlet::create($data);

            /*
             * Give the branch to the people who already had every other one.
             *
             * Outlet access is an explicit list per user, so a branch nobody is
             * attached to is invisible to everyone except the "all outlets"
             * accounts — including the person who just created it. Reported as a
             * new branch missing from the employee form's picker; it was missing
             * from every outlet dropdown in the product, because they all read
             * User::accessibleOutlets().
             *
             * Attaching the creator alone would not be enough: an owner and an
             * operations manager who each held all four branches would end up
             * holding four of five, silently losing sight of the newest one. So
             * anyone with COMPLETE coverage before keeps complete coverage —
             * which includes the creator, who cannot make a branch they cannot
             * see. Everyone else is unchanged: a branch manager on one outlet
             * does not inherit a second one because head office opened it.
             */
            $this->grantToWhoeverHadThemAll($outlet);

            session()->flash('success', 'Branch created.');
        }

        $this->closeModal();
    }

    /**
     * Attach a new outlet to every company user who held all the previous ones.
     *
     * "All outlets" users are skipped: their flag already covers the new branch,
     * and writing pivot rows for them would make the list say something the flag
     * does not depend on. The creator is included by the same rule they are
     * measured against — they held every outlet that existed a moment ago, or
     * they could not have reached this screen's own outlet scope.
     */
    private function grantToWhoeverHadThemAll(Outlet $outlet): void
    {
        $others = Outlet::where('company_id', $outlet->company_id)
            ->where('id', '!=', $outlet->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $users = User::where('company_id', $outlet->company_id)
            ->where('can_view_all_outlets', false)
            ->get();

        foreach ($users as $user) {
            $held = DB::table('outlet_user')
                ->join('outlets', 'outlets.id', '=', 'outlet_user.outlet_id')
                ->where('outlet_user.user_id', $user->id)
                ->where('outlets.company_id', $outlet->company_id)
                ->pluck('outlets.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // The first branch in a company has no "all the others" to compare
            // against, so nobody qualifies by coverage — the creator does.
            $hadThemAll = $others
                ? ! array_diff($others, $held)
                : $user->id === Auth::id();

            if ($hadThemAll) {
                $user->outlets()->syncWithoutDetaching([$outlet->id]);
            }
        }

        // Belt and braces: whoever made it can always reach it. Skipped for an
        // "all outlets" account, whose flag already covers it.
        $creator = Auth::user();

        if ($creator && ! $creator->can_view_all_outlets) {
            $creator->outlets()->syncWithoutDetaching([$outlet->id]);
        }
    }

    public function toggleActive(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $outlet->update(['is_active' => ! $outlet->is_active]);
    }

    public function delete(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);

        if ($outlet->users()->count() > 0) {
            session()->flash('error', 'Cannot delete — this branch still has assigned users.');
            return;
        }

        $outlet->delete();
        session()->flash('success', 'Branch deleted.');
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->withCount('users')
            ->with(['defaultKitchen:id,name', 'defaultCpu:id,name'])
            ->orderBy('name')
            ->get();

        // Facility options for routing assignment (CompanyScope keeps these tenant-safe).
        $kitchens = CentralKitchen::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $cpus     = CentralPurchasingUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $cpuMode  = Auth::user()->company?->ordering_mode === 'cpu';

        return view('livewire.settings.outlets', compact('outlets', 'kitchens', 'cpus', 'cpuMode'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Branches']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->code      = '';
        $this->phone     = '';
        $this->address   = '';
        $this->country   = '';
        $this->state     = '';
        $this->is_active = true;
        $this->default_kitchen_id = null;
        $this->default_cpu_id     = null;
        $this->resetValidation();
    }
}
