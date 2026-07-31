<?php

namespace App\Livewire\Labels;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Manager view: who can sign in to the staff label app.
 *
 * Access is exactly "has a PIN". Granting is setting one, revoking is
 * clearing it — no separate flag to fall out of step with reality.
 *
 * A generated PIN is shown ONCE, here, and never again. It is stored
 * hashed, so this screen genuinely cannot show it later; the only recovery
 * is to issue a new one. That is the right trade for a credential that
 * opens a kitchen's printing.
 */
class StaffAccess extends Component
{
    public string $search = '';

    public ?int $outletFilter = null;

    /** [employee_id => plain PIN] — held only for this render, never stored. */
    public array $justIssued = [];

    public ?int $settingFor = null;

    public string $manualPin = '';

    public function updatedSearch(): void
    {
        $this->justIssued = [];
    }

    /** Generate a random 4-digit PIN and reveal it once. */
    public function issuePin(int $id): void
    {
        $employee = $this->employee($id);

        // Avoids 0000/1234-style values without needing a blocklist: still
        // uniform over the range, just not the obvious ones.
        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (in_array($pin, ['0000', '1111', '1234', '4321', '9999'], true));

        $employee->setLabelPin($pin);

        $this->justIssued = [$employee->id => $pin];
        $this->settingFor = null;

        session()->flash('success', 'PIN issued for ' . $employee->name . '. Write it down now — it cannot be shown again.');
    }

    public function openManual(int $id): void
    {
        $this->settingFor = $id;
        $this->manualPin  = '';
        $this->justIssued = [];
        $this->resetValidation();
    }

    public function closeManual(): void
    {
        $this->settingFor = null;
        $this->manualPin  = '';
    }

    public function saveManualPin(): void
    {
        if (! preg_match('/^\d{4,6}$/', $this->manualPin)) {
            $this->addError('manualPin', 'Use 4 to 6 digits.');

            return;
        }

        $employee = $this->employee((int) $this->settingFor);
        $employee->setLabelPin($this->manualPin);

        $this->justIssued = [$employee->id => $this->manualPin];
        $this->closeManual();

        session()->flash('success', 'PIN set for ' . $employee->name . '.');
    }

    public function revoke(int $id): void
    {
        $employee = $this->employee($id);
        $employee->setLabelPin(null);

        $this->justIssued = [];

        session()->flash('success', $employee->name . ' can no longer sign in to the label app.');
    }

    public function render()
    {
        $employees = Employee::where('is_active', true)
            ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', $this->outletFilter))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->with('outlet')
            ->orderBy('name')
            ->get();

        return view('livewire.labels.staff-access', [
            'employees' => $employees,
            'outlets'   => Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
            'appUrl'    => $this->staffAppUrl(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label staff access']);
    }

    /** The address to give staff, so a manager doesn't have to work it out. */
    private function staffAppUrl(): string
    {
        $company = Company::find(Auth::user()->company_id);
        $domain  = config('app.domain');

        if (! $domain || ! $company?->slug) {
            return url('/labels-staff/login');
        }

        return 'https://' . $company->slug . '.' . $domain . '/labels';
    }

    /** Scoped by the model's CompanyScope — never trust a posted id alone. */
    private function employee(int $id): Employee
    {
        return Employee::findOrFail($id);
    }
}
