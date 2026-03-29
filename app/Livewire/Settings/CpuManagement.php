<?php

namespace App\Livewire\Settings;

use App\Models\CentralPurchasingUnit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CpuManagement extends Component
{
    public bool $showForm = false;
    public ?int $editId   = null;

    public string $name           = '';
    public string $code           = '';
    public string $address        = '';
    public string $contact_person = '';
    public string $email          = '';
    public string $phone          = '';
    public string $delivery_mode  = 'via_cpu';
    public bool   $is_active      = true;

    public array $assignedUserIds = [];

    protected function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'delivery_mode'  => 'required|in:via_cpu,direct_to_outlet',
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
        $cpu = CentralPurchasingUnit::with('users')->findOrFail($id);
        $this->editId         = $cpu->id;
        $this->name           = $cpu->name;
        $this->code           = $cpu->code ?? '';
        $this->address        = $cpu->address ?? '';
        $this->contact_person = $cpu->contact_person ?? '';
        $this->email          = $cpu->email ?? '';
        $this->phone          = $cpu->phone ?? '';
        $this->delivery_mode  = $cpu->delivery_mode;
        $this->is_active      = $cpu->is_active;
        $this->assignedUserIds = $cpu->users->pluck('id')->map(fn ($id) => (string) $id)->toArray();
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
                'address'        => $this->address ?: null,
                'contact_person' => $this->contact_person ?: null,
                'email'          => $this->email ?: null,
                'phone'          => $this->phone ?: null,
                'delivery_mode'  => $this->delivery_mode,
                'is_active'      => $this->is_active,
            ];

            if ($this->editId) {
                $cpu = CentralPurchasingUnit::findOrFail($this->editId);
                $cpu->update($data);
            } else {
                $cpu = CentralPurchasingUnit::create($data);
            }

            // Sync assigned users
            $syncData = [];
            foreach ($this->assignedUserIds as $userId) {
                $syncData[(int) $userId] = ['role' => 'staff'];
            }
            $cpu->users()->sync($syncData);
        });

        $this->showForm = false;
        $this->resetForm();

        session()->flash('success', $this->editId ? 'CPU updated.' : 'CPU created.');
    }

    public function delete(int $id): void
    {
        $cpu = CentralPurchasingUnit::findOrFail($id);

        // Check if CPU has any active purchase requests or orders
        if ($cpu->purchaseRequests()->whereNotIn('status', ['cancelled'])->exists()) {
            session()->flash('error', 'Cannot delete — this CPU has active purchase requests.');
            return;
        }

        $cpu->delete();
        session()->flash('success', 'CPU deleted.');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->name = '';
        $this->code = '';
        $this->address = '';
        $this->contact_person = '';
        $this->email = '';
        $this->phone = '';
        $this->delivery_mode = 'via_cpu';
        $this->is_active = true;
        $this->assignedUserIds = [];
    }

    public function render()
    {
        $cpus = CentralPurchasingUnit::with('users')
            ->orderBy('name')
            ->get();

        $companyUsers = User::where('company_id', Auth::user()->company_id)
            ->orderBy('name')
            ->get();

        return view('livewire.settings.cpu-management', [
            'cpus'         => $cpus,
            'companyUsers' => $companyUsers,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Central Purchasing Units']);
    }
}
