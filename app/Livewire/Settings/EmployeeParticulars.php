<?php

namespace App\Livewire\Settings;

use App\Models\Employee;
use App\Models\HrOption;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The pick-lists behind the employee particulars: sex, nationality, race,
 * religion, marital status, education.
 *
 * One screen for all of them — they are the same CRUD over the same shape, and
 * six settings cards for six two-column lists would bury the ones that matter.
 * The type tabs are the whole navigation.
 *
 * RENAMING carries the employees with it. `employees.nationality` and friends
 * store the value rather than a key, so a rename that did not cascade would
 * leave everybody holding a value the picker no longer offers.
 */
class EmployeeParticulars extends Component
{
    #[Url(as: 'list')]
    public string $type = 'gender';

    public bool $showInactive = false;

    public bool $showForm  = false;
    public ?int $editingId = null;

    public string $f_name      = '';
    public string $f_code      = '';
    public bool   $f_is_active = true;

    public function mount(): void
    {
        if (! array_key_exists($this->type, HrOption::TYPES)) {
            $this->type = array_key_first(HrOption::TYPES);
        }

        // Choosing forty values by hand is not a decision worth blocking on.
        HrOption::seedDefaults(Auth::user()->company_id);
    }

    public function selectType(string $type): void
    {
        if (! array_key_exists($type, HrOption::TYPES)) {
            return;
        }

        $this->type = $type;
        $this->showForm = false;
        $this->resetForm();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $option = HrOption::ofType($this->type)->findOrFail($id);

        $this->editingId   = $option->id;
        $this->f_name      = $option->name;
        $this->f_code      = (string) $option->code;
        $this->f_is_active = (bool) $option->is_active;

        $this->showForm = true;
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'f_name' => [
                'required', 'string', 'max:60',
                \Illuminate\Validation\Rule::unique('hr_options', 'name')
                    ->where('company_id', $companyId)
                    ->where('type', $this->type)
                    ->ignore($this->editingId),
            ],
            'f_code' => 'nullable|string|max:20',
        ], [], [
            'f_name' => strtolower(HrOption::labelFor($this->type)), 'f_code' => 'code',
        ]);

        $data = [
            'company_id' => $companyId,
            'type'       => $this->type,
            'name'       => trim($this->f_name),
            'code'       => trim($this->f_code) ?: null,
            'is_active'  => $this->f_is_active,
        ];

        if ($this->editingId) {
            $option  = HrOption::ofType($this->type)->findOrFail($this->editingId);
            $oldName = $option->name;
            $option->update($data);

            $message = HrOption::labelFor($this->type) . ' updated.';

            // Carry the employees across, or a rename strands every one of them
            // on a value the picker no longer offers.
            if ($oldName !== $data['name']) {
                $moved = Employee::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where(HrOption::columnFor($this->type), $oldName)
                    ->update([HrOption::columnFor($this->type) => $data['name']]);

                if ($moved > 0) {
                    $message .= " {$moved} employee(s) moved to the new value.";
                }
            }
        } else {
            $data['sort_order'] = (int) HrOption::ofType($this->type)->max('sort_order') + 1;
            HrOption::create($data);
            $message = HrOption::labelFor($this->type) . ' added.';
        }

        session()->flash('success', $message);

        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Retired rather than deleted once anybody holds the value.
     *
     * Switching off takes it out of the picker for new choices while leaving
     * the records that already hold it saying what they say.
     */
    public function toggleActive(int $id): void
    {
        $option = HrOption::ofType($this->type)->findOrFail($id);
        $option->update(['is_active' => ! $option->is_active]);
    }

    public function delete(int $id): void
    {
        $option = HrOption::ofType($this->type)->findOrFail($id);

        if (($count = $option->employeeCount()) > 0) {
            session()->flash('error', "{$count} employee(s) are recorded as {$option->name} — switch it off instead of deleting, or their records lose the value.");
            return;
        }

        $option->delete();
        session()->flash('success', 'Removed from the list.');
    }

    /** Put back any default this list no longer has. Matched by name. */
    public function restoreDefaults(): void
    {
        $companyId = Auth::user()->company_id;
        $existing  = HrOption::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('type', $this->type)
            ->pluck('name')->all();
        $next  = (int) HrOption::ofType($this->type)->max('sort_order') + 1;
        $added = 0;

        foreach (HrOption::TYPES[$this->type]['defaults'] as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            HrOption::create([
                'company_id' => $companyId,
                'type'       => $this->type,
                'name'       => $name,
                'sort_order' => $next++,
                'is_active'  => true,
            ]);
            $added++;
        }

        session()->flash('success', $added > 0
            ? "{$added} default value(s) restored."
            : 'Nothing to restore — every default is already listed.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'f_name', 'f_code']);
        $this->f_is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $column  = HrOption::columnFor($this->type);
        $options = HrOption::ofType($this->type)->ordered()
            ->when(! $this->showInactive, fn ($q) => $q->active())
            ->get();

        // One query per page rather than one per row: the counts decide which
        // values may be deleted.
        $usage = Employee::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->whereNotNull($column)
            ->selectRaw("{$column} as value, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'value');

        // Values on employee records that match nothing in the list — left
        // behind by a delete, or imported. This is the only screen that can
        // fix them: add the value back and those records rejoin the picker.
        $known   = HrOption::ofType($this->type)->pluck('name')->all();
        $offList = $usage->reject(fn ($count, $value) => in_array($value, $known, true));

        return view('livewire.settings.employee-particulars', [
            'options' => $options,
            'usage'   => $usage,
            'offList' => $offList,
            'meta'    => HrOption::TYPES[$this->type],
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Employee Particulars']);
    }
}
