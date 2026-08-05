<?php

namespace App\Livewire\Hr;

use App\Models\Outlet;
use App\Models\RosterEntry;
use App\Models\Section;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The company's shift templates.
 *
 * Managed here, applied in the Duty Roster. Behind roster.settings, the same
 * permission as stations and roster settings — whoever configures the roster
 * configures its shifts.
 */
class Shifts extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name          = '';
    public string $code          = '';
    public string $start_time    = '08:00';
    public string $end_time      = '17:00';
    public string $rest_duration = '60';
    public string $normal_hours  = '';
    public ?int   $outlet_id     = null;
    public ?int   $section_id    = null;
    public string $sort_order    = '0';
    public bool   $is_active     = true;

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:60',
            'code'          => 'nullable|string|max:12',
            'start_time'    => 'required|date_format:H:i',
            'end_time'      => 'required|date_format:H:i',
            'rest_duration' => 'required|integer|min:0|max:480',
            'normal_hours'  => 'nullable|numeric|min:0|max:24',
            'outlet_id'     => 'nullable|integer|exists:outlets,id',
            'section_id'    => 'nullable|integer|exists:sections,id',
            'sort_order'    => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $shift = Shift::findOrFail($id);

        $this->editingId     = $shift->id;
        $this->name          = $shift->name;
        $this->code          = $shift->code ?? '';
        $this->start_time    = $shift->start_time->format('H:i');
        $this->end_time      = $shift->end_time->format('H:i');
        $this->rest_duration = (string) $shift->rest_duration;
        $this->normal_hours  = $shift->normal_hours !== null ? (string) (float) $shift->normal_hours : '';
        $this->outlet_id     = $shift->outlet_id;
        $this->section_id    = $shift->section_id;
        $this->sort_order    = (string) $shift->sort_order;
        $this->is_active     = $shift->is_active;
        $this->showModal     = true;
    }

    public function save(): void
    {
        $this->validate();

        $accessible = Auth::user()->accessibleOutletIds();
        if ($this->outlet_id && ! in_array((int) $this->outlet_id, $accessible, true)) {
            $this->addError('outlet_id', 'You do not have access to that outlet.');
            return;
        }

        $data = [
            'name'          => $this->name,
            'code'          => $this->code ?: null,
            'start_time'    => $this->start_time,
            'end_time'      => $this->end_time,
            'rest_duration' => (int) $this->rest_duration,
            'normal_hours'  => $this->normal_hours !== '' ? round((float) $this->normal_hours, 2) : null,
            'outlet_id'     => $this->outlet_id ?: null,
            'section_id'    => $this->section_id ?: null,
            'sort_order'    => (int) $this->sort_order,
            'is_active'     => $this->is_active,
        ];

        if ($this->editingId) {
            Shift::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Shift updated. Rosters already built keep the times they were saved with.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            Shift::create($data);
            session()->flash('success', 'Shift added.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $shift = Shift::findOrFail($id);

        // Rosters keep their own copy of the times, so deleting only loses the
        // label. Still worth saying how many, rather than silently unlabelling
        // a quarter of the year.
        $used = RosterEntry::where('shift_id', $shift->id)->count();
        if ($used > 0) {
            session()->flash('error', "\"{$shift->name}\" is used on {$used} roster " . ($used === 1 ? 'entry' : 'entries') . '. Deactivate it instead — deleting would strip its name off them.');
            return;
        }

        $shift->delete();
        session()->flash('success', 'Shift deleted.');
    }

    public function toggleActive(int $id): void
    {
        $shift = Shift::findOrFail($id);
        $shift->update(['is_active' => ! $shift->is_active]);
    }

    public function render()
    {
        $user = Auth::user();

        $shifts = Shift::with(['outlet:id,name', 'section:id,name'])->ordered()->get();

        $outlets = Outlet::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereIn('id', $user->accessibleOutletIds())
            ->orderBy('name')
            ->get();

        $sections = Section::active()->ordered()->get();

        $usage = RosterEntry::selectRaw('shift_id, count(*) as total')
            ->whereNotNull('shift_id')
            ->groupBy('shift_id')
            ->pluck('total', 'shift_id');

        return view('livewire.hr.shifts', compact('shifts', 'outlets', 'sections', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Shifts']);
    }

    private function resetForm(): void
    {
        $this->editingId     = null;
        $this->name          = '';
        $this->code          = '';
        $this->start_time    = '08:00';
        $this->end_time      = '17:00';
        $this->rest_duration = '60';
        $this->normal_hours  = '';
        $this->outlet_id     = null;
        $this->section_id    = null;
        $this->sort_order    = '0';
        $this->is_active     = true;
        $this->resetValidation();
    }
}
