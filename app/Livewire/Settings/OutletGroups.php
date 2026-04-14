<?php

namespace App\Livewire\Settings;

use App\Models\Outlet;
use App\Models\OutletGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OutletGroups extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public int $sort_order = 0;
    public bool $is_active = true;
    public array $outletIds = [];

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
            'outletIds'  => 'array',
            'outletIds.*'=> 'integer|exists:outlets,id',
        ];
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'sort_order', 'is_active', 'outletIds']);
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $group = OutletGroup::with('outlets')->findOrFail($id);
        $this->editingId  = $group->id;
        $this->name       = $group->name;
        $this->sort_order = $group->sort_order;
        $this->is_active  = $group->is_active;
        $this->outletIds  = $group->outlets->pluck('id')->toArray();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'company_id' => Auth::user()->company_id,
            'name'       => $this->name,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->editingId) {
            $group = OutletGroup::findOrFail($this->editingId);
            $group->update($data);
        } else {
            $group = OutletGroup::create($data);
        }

        $group->outlets()->sync($this->outletIds);

        session()->flash('success', $this->editingId ? 'Outlet group updated.' : 'Outlet group created.');
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'sort_order', 'is_active', 'outletIds']);
    }

    public function delete(int $id): void
    {
        OutletGroup::findOrFail($id)->delete();
        session()->flash('success', 'Outlet group deleted.');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'sort_order', 'is_active', 'outletIds']);
    }

    public function render()
    {
        $groups = OutletGroup::with('outlets')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.settings.outlet-groups', [
            'groups'  => $groups,
            'outlets' => $outlets,
        ])->layout('layouts.app', ['title' => 'Outlet Groups']);
    }
}
