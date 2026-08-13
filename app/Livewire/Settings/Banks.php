<?php

namespace App\Livewire\Settings;

use App\Models\Bank;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Traits\RequiresActiveCompany;

/**
 * Banks: the list the employee form's Bank picker offers.
 *
 * Seeded from the IBG participating list so nobody has to type 41 banks, then
 * owned by the company — the list moves faster than deploys do when banks
 * merge, rename or join IBG.
 *
 * The delicate part is RENAMING. `employees.bank_name` stores the name, not a
 * key, so a rename here has to carry the employees with it or every one of them
 * silently falls off the picker and out of the payment file's bank column.
 * save() does that in the same breath as the rename.
 */
class Banks extends Component
{
    use RequiresActiveCompany;

    public string $search = '';
    public bool   $showInactive = false;

    public bool $showForm  = false;
    public ?int $editingId = null;

    public string $f_name      = '';
    public string $f_bic       = '';
    public bool   $f_is_active = true;

    public function mount(): void
    {
        // A company with no banks at all has an empty picker on the employee
        // form, and choosing 41 names is not a decision worth blocking on.
        Bank::seedDefaults($this->requireActiveCompany());
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $bank = Bank::findOrFail($id);

        $this->editingId   = $bank->id;
        $this->f_name      = $bank->name;
        $this->f_bic       = (string) $bank->bic;
        $this->f_is_active = (bool) $bank->is_active;

        $this->showForm = true;
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'f_name' => [
                'required', 'string', 'max:60',
                // The name IS the stored value on an employee, so a duplicate
                // would make the picked bank ambiguous.
                \Illuminate\Validation\Rule::unique('banks', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            // 8 or 11 characters, letters and digits — the SWIFT format. Left
            // optional: a bank added by hand may not have one to hand, and
            // refusing the row over it sends the user back to free text.
            'f_bic' => 'nullable|string|regex:/^[A-Za-z0-9]{8}([A-Za-z0-9]{3})?$/',
        ], [
            'f_bic.regex' => 'A BIC is 8 or 11 letters and digits, e.g. MBBEMYKL.',
        ], [
            'f_name' => 'bank name', 'f_bic' => 'BIC',
        ]);

        $data = [
            'company_id' => $companyId,
            'name'       => trim($this->f_name),
            'bic'        => strtoupper(trim($this->f_bic)) ?: null,
            'is_active'  => $this->f_is_active,
        ];

        if ($this->editingId) {
            $bank    = Bank::findOrFail($this->editingId);
            $oldName = $bank->name;
            $bank->update($data);

            $message = 'Bank updated.';

            // Carry the employees across, or a rename orphans every one of
            // them: their stored name would no longer match anything in the
            // picker, and the form would show it as an off-list value.
            if ($oldName !== $data['name']) {
                $moved = Employee::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('bank_name', $oldName)
                    ->update(['bank_name' => $data['name']]);

                if ($moved > 0) {
                    $message .= " {$moved} employee(s) moved to the new name.";
                }
            }
        } else {
            $data['sort_order'] = (int) Bank::max('sort_order') + 1;
            Bank::create($data);
            $message = 'Bank added.';
        }

        session()->flash('success', $message);

        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Retired rather than deleted once anybody is paid into it.
     *
     * Switching off takes the bank out of the picker for new choices while
     * leaving the employees who already bank there naming it correctly.
     */
    public function toggleActive(int $id): void
    {
        $bank = Bank::findOrFail($id);
        $bank->update(['is_active' => ! $bank->is_active]);
    }

    public function delete(int $id): void
    {
        $bank = Bank::findOrFail($id);

        if (($count = $bank->employeeCount()) > 0) {
            session()->flash('error', "{$count} employee(s) are paid into {$bank->name} — switch it off instead of deleting, or their records lose the bank's name.");
            return;
        }

        $bank->delete();
        session()->flash('success', 'Bank deleted.');
    }

    /**
     * Put back any standard IBG bank this company no longer has.
     *
     * Matched by NAME, so a bank that was renamed here stays renamed and a
     * deliberately deleted one comes back — which is the point: this is the
     * "I have edited myself into a corner" button, not a sync.
     */
    public function restoreStandardList(): void
    {
        $companyId = Auth::user()->company_id;
        $existing  = Bank::withoutGlobalScopes()->where('company_id', $companyId)->pluck('name')->all();
        $next      = (int) Bank::max('sort_order') + 1;
        $added     = 0;

        foreach (Bank::IBG_BANKS as [$name, $bic]) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            Bank::create([
                'company_id' => $companyId,
                'name'       => $name,
                'bic'        => $bic,
                'sort_order' => $next++,
                'is_active'  => true,
            ]);
            $added++;
        }

        session()->flash('success', $added > 0
            ? "{$added} standard bank(s) restored."
            : 'Nothing to restore — every standard IBG bank is already listed.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'f_name', 'f_bic']);
        $this->f_is_active = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $banks = Bank::ordered()
            ->when(! $this->showInactive, fn ($q) => $q->active())
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('bic', 'like', $term));
            })
            ->get();

        // One query for the whole page rather than one per row: the counts
        // decide which banks may be deleted, and there are 40-odd of them.
        $usage = Employee::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->whereNotNull('bank_name')
            ->selectRaw('bank_name, COUNT(*) as total')
            ->groupBy('bank_name')
            ->pluck('total', 'bank_name');

        // Bank names on employees that match nothing in the list — typed in
        // before the picker existed, or left behind by a delete. Surfaced here
        // because this is the only screen that can fix them: add the bank and
        // the employees rejoin the picker by name.
        $known    = Bank::withoutGlobalScopes()->where('company_id', Auth::user()->company_id)->pluck('name')->all();
        $offList  = $usage->reject(fn ($count, $name) => in_array($name, $known, true));

        return view('livewire.settings.banks', [
            'banks'      => $banks,
            'usage'      => $usage,
            'offList'    => $offList,
            'totalBanks' => Bank::count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Banks']);
    }
}
