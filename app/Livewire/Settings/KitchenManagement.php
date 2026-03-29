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

    protected function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:20',
            'outlet_id'      => 'nullable|exists:outlets,id',
            'address'        => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'is_active'      => 'boolean',
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
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $companyId = Auth::user()->company_id;

        DB::transaction(function () use ($companyId) {
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

            // Also assign kitchen users to the linked outlet (so they can use purchasing, inventory, etc.)
            if ($kitchen->outlet_id) {
                foreach ($this->assignedUserIds as $userId) {
                    DB::table('outlet_user')->updateOrInsert(
                        ['outlet_id' => $kitchen->outlet_id, 'user_id' => (int) $userId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        });

        $this->showForm = false;
        $this->resetForm();

        session()->flash('success', $this->editId ? 'Kitchen updated.' : 'Kitchen created.');
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
    }

    public function render()
    {
        $kitchens = CentralKitchen::with(['users', 'outlet'])
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
