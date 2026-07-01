<?php

namespace App\Livewire\Settings;

use App\Models\CentralKitchen;
use App\Models\Outlet;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class KitchenManagement extends Component
{
    public bool $showForm = false;
    public ?int $editId   = null;

    public string $name           = '';
    public string $code           = '';
    public ?int   $outlet_id      = null;
    public string $address        = '';
    public string $contact_person = '';
    public string $email          = '';
    public string $phone          = '';
    public bool   $is_active      = true;

    public array $assignedUserIds = [];
    public array $servedOutletIds = [];

    protected function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'code'             => 'nullable|string|max:20',
            'outlet_id'        => 'nullable|exists:outlets,id',
            'address'          => 'nullable|string',
            'contact_person'   => 'nullable|string|max:100',
            'email'            => 'nullable|email|max:255',
            'phone'            => 'nullable|string|max:30',
            'is_active'        => 'boolean',
            'servedOutletIds'  => 'array',
            'servedOutletIds.*' => 'exists:outlets,id',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $kitchen = CentralKitchen::with('users')->findOrFail($id);
        $this->editId         = $kitchen->id;
        $this->name           = $kitchen->name;
        $this->code           = $kitchen->code ?? '';
        $this->outlet_id      = $kitchen->outlet_id;
        $this->address        = $kitchen->address ?? '';
        $this->contact_person = $kitchen->contact_person ?? '';
        $this->email          = $kitchen->email ?? '';
        $this->phone          = $kitchen->phone ?? '';
        $this->is_active      = $kitchen->is_active;
        $this->assignedUserIds = $kitchen->users->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->servedOutletIds = Outlet::where('default_kitchen_id', $kitchen->id)
            ->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $companyId  = Auth::user()->company_id;
        $wasEditing = (bool) $this->editId;
        $reassigned = [];

        DB::transaction(function () use ($companyId, &$reassigned) {
            $data = [
                'company_id'     => $companyId,
                'name'           => $this->name,
                'code'           => $this->code ?: null,
                'outlet_id'      => $this->outlet_id ?: null,
                'address'        => $this->address ?: null,
                'contact_person' => $this->contact_person ?: null,
                'email'          => $this->email ?: null,
                'phone'          => $this->phone ?: null,
                'is_active'      => $this->is_active,
            ];

            if ($this->editId) {
                $kitchen = CentralKitchen::findOrFail($this->editId);
                $kitchen->update($data);
            } else {
                $kitchen = CentralKitchen::create($data);
            }

            // Sync assigned users to kitchen
            $syncData = [];
            foreach ($this->assignedUserIds as $userId) {
                $syncData[(int) $userId] = ['role' => 'staff'];
            }
            $kitchen->users()->sync($syncData);

            // Also assign kitchen users to the base outlet (so they can use purchasing, inventory, etc.)
            if ($kitchen->outlet_id) {
                foreach ($this->assignedUserIds as $userId) {
                    DB::table('outlet_user')->updateOrInsert(
                        ['outlet_id' => $kitchen->outlet_id, 'user_id' => (int) $userId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            // Sync the outlets this kitchen serves (reverse of outlets.default_kitchen_id)
            $reassigned = $this->syncServedOutlets($kitchen, $companyId);
        });

        $this->showForm = false;
        $this->resetForm();

        $message = $wasEditing ? 'Kitchen updated.' : 'Kitchen created.';
        if (! empty($reassigned)) {
            $message .= ' Reassigned from another kitchen: ' . implode(', ', $reassigned) . '.';
        }
        session()->flash('success', $message);
    }

    /**
     * Point the selected outlets' default_kitchen_id at this kitchen, and clear it
     * from any outlet that was previously served here but is no longer selected.
     * Returns the names of outlets moved away from a different kitchen.
     */
    private function syncServedOutlets(CentralKitchen $kitchen, int $companyId): array
    {
        $selected = array_map('intval', $this->servedOutletIds);

        $current = Outlet::where('company_id', $companyId)
            ->where('default_kitchen_id', $kitchen->id)
            ->pluck('id')->all();

        $toAssign = array_values(array_diff($selected, $current));
        $toClear  = array_values(array_diff($current, $selected));

        $reassigned = empty($toAssign) ? [] : Outlet::where('company_id', $companyId)
            ->whereIn('id', $toAssign)
            ->whereNotNull('default_kitchen_id')
            ->where('default_kitchen_id', '!=', $kitchen->id)
            ->pluck('name')->all();

        if (! empty($selected)) {
            Outlet::where('company_id', $companyId)->whereIn('id', $selected)
                ->update(['default_kitchen_id' => $kitchen->id]);
        }
        if (! empty($toClear)) {
            Outlet::where('company_id', $companyId)->whereIn('id', $toClear)
                ->update(['default_kitchen_id' => null]);
        }

        return $reassigned;
    }

    public function delete(int $id): void
    {
        $kitchen = CentralKitchen::findOrFail($id);

        // Guard: check for active production orders
        if ($kitchen->productionOrders()->whereNotIn('status', ['cancelled', 'completed'])->exists()) {
            session()->flash('error', 'Cannot delete — this kitchen has active production orders.');
            return;
        }

        $kitchen->delete();
        session()->flash('success', 'Kitchen deleted.');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->name = '';
        $this->code = '';
        $this->outlet_id = null;
        $this->address = '';
        $this->contact_person = '';
        $this->email = '';
        $this->phone = '';
        $this->is_active = true;
        $this->assignedUserIds = [];
        $this->servedOutletIds = [];
    }

    public function render()
    {
        $kitchens = CentralKitchen::with(['users', 'outlet', 'servedOutlets:id,name,default_kitchen_id'])
            ->orderBy('name')
            ->get();

        $companyUsers = User::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.settings.kitchen-management', [
            'kitchens'     => $kitchens,
            'companyUsers' => $companyUsers,
            'outlets'      => $outlets,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Central Kitchens']);
    }
}
