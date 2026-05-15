<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\RosterStation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RosterStations extends Component
{
    public ?int $outletId = null;

    // Form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $f_name = '';
    public string $f_description = '';
    public int $f_sort_order = 0;
    public bool $f_is_active = true;

    protected function rules(): array
    {
        return [
            'f_name' => 'required|string|max:100',
            'f_description' => 'nullable|string|max:255',
            'f_sort_order' => 'integer|min:0',
            'f_is_active' => 'boolean',
        ];
    }

    public function mount(): void
    {
        $this->outletId = Auth::user()?->activeOutletId();
    }

    public function updatedOutletId(): void
    {
        $this->closeForm();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->f_sort_order = RosterStation::where('outlet_id', $this->outletId)->max('sort_order') + 1;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $station = RosterStation::findOrFail($id);
        $this->editingId = $station->id;
        $this->f_name = $station->name;
        $this->f_description = $station->description ?? '';
        $this->f_sort_order = $station->sort_order;
        $this->f_is_active = $station->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->f_name = '';
        $this->f_description = '';
        $this->f_sort_order = 0;
        $this->f_is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $station = RosterStation::findOrFail($this->editingId);
            $station->update([
                'name' => $this->f_name,
                'description' => $this->f_description ?: null,
                'sort_order' => $this->f_sort_order,
                'is_active' => $this->f_is_active,
            ]);
            session()->flash('success', 'Station updated.');
        } else {
            RosterStation::create([
                'outlet_id' => $this->outletId,
                'name' => $this->f_name,
                'description' => $this->f_description ?: null,
                'sort_order' => $this->f_sort_order,
                'is_active' => $this->f_is_active,
            ]);
            session()->flash('success', 'Station created.');
        }

        $this->closeForm();
    }

    public function toggleActive(int $id): void
    {
        $station = RosterStation::findOrFail($id);
        $station->update(['is_active' => !$station->is_active]);
    }

    public function delete(int $id): void
    {
        $station = RosterStation::findOrFail($id);

        // Check if station is used in any roster entries
        if ($station->entries()->exists()) {
            session()->flash('error', 'Cannot delete station that is assigned to roster entries.');
            return;
        }

        $station->delete();
        session()->flash('success', 'Station deleted.');
    }

    public function moveUp(int $id): void
    {
        $station = RosterStation::findOrFail($id);
        $prev = RosterStation::where('outlet_id', $this->outletId)
            ->where('sort_order', '<', $station->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($prev) {
            $tempOrder = $station->sort_order;
            $station->update(['sort_order' => $prev->sort_order]);
            $prev->update(['sort_order' => $tempOrder]);
        }
    }

    public function moveDown(int $id): void
    {
        $station = RosterStation::findOrFail($id);
        $next = RosterStation::where('outlet_id', $this->outletId)
            ->where('sort_order', '>', $station->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            $tempOrder = $station->sort_order;
            $station->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $tempOrder]);
        }
    }

    protected function accessibleOutlets()
    {
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->orderBy('name')->get();
        }
        return $user->outlets()->orderBy('name')->get();
    }

    public function render()
    {
        $stations = collect();
        if ($this->outletId) {
            $stations = RosterStation::where('outlet_id', $this->outletId)
                ->ordered()
                ->get();
        }

        return view('livewire.hr.roster-stations', [
            'outlets' => $this->accessibleOutlets(),
            'stations' => $stations,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Roster Stations']);
    }
}
