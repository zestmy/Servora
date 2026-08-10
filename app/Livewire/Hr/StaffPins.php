<?php

namespace App\Livewire\Hr;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Manager view: who can sign in to the staff apps.
 *
 * Lives in HR rather than under Labels, where it started. One PIN now opens
 * both staff apps — label printing and clock-in — and the person who decides
 * whether somebody can clock in is the person who manages that employee, not
 * whoever administers the label printers.
 *
 * Access is exactly "has a PIN". Granting is setting one, revoking is
 * clearing it — no separate flag to fall out of step with reality.
 *
 * A generated PIN is shown ONCE, here, and never again. It is stored
 * hashed, so this screen genuinely cannot show it later; the only recovery
 * is to issue a new one. That is the right trade for a credential that now
 * opens attendance as well as printing.
 */
class StaffPins extends Component
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
        // Guarded here as well as on save: a panel that opens and then refuses
        // reads as a broken screen rather than a boundary.
        $this->employee($id);

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

        session()->flash('success', $employee->name . ' can no longer sign in to the staff apps.');
    }

    public function render()
    {
        $employees = Employee::where('is_active', true)
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', $this->outletFilter))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->with('outlet')
            ->orderBy('name')
            ->get();

        return view('livewire.hr.staff-pins', [
            'employees' => $employees,
            // The filter cannot offer a branch whose staff the list will not
            // show, or it reads as an empty branch rather than one that is
            // none of your business.
            'outlets'   => Auth::user()->accessibleOutlets()->orderBy('name')->get(),
            'labelsUrl' => $this->staffAppUrl('labels', 'labels-staff'),
            // /staff, not /clock. The old prefix still redirects, but this is
            // the address a manager reads out loud and writes on a noticeboard,
            // and one that arrives via a redirect outlives the redirect.
            'clockUrl'  => $this->staffAppUrl('staff', 'staff'),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Staff PINs']);
    }

    /**
     * The address to give staff, so a manager doesn't have to work it out.
     *
     * Both apps are built the same way: a company subdomain in production,
     * a plain path locally where there is no subdomain to constrain on.
     */
    private function staffAppUrl(string $path, string $localPath): string
    {
        $company = Company::find(Auth::user()->company_id);
        $domain  = config('app.domain');

        if (! $domain || ! $company?->slug) {
            return url('/' . $localPath . '/login');
        }

        return 'https://' . $company->slug . '.' . $domain . '/' . $path;
    }

    /**
     * Outlets whose staff this person may administer.
     *
     * "All outlets" accounts and system roles get the whole company through
     * User::accessibleOutlets(), which is what makes a head-office manager work
     * without a special case here.
     *
     * @return array<int, int>
     */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    /**
     * Scoped by CompanyScope AND by outlet — never trust a posted id alone.
     *
     * Every write on this screen funnels through here, which is the point: a PIN
     * opens clock-in as well as label printing, so issuing one for another
     * branch's staff means putting attendance for a shift you do not run into
     * somebody's hands. The list was already company-wide, so a manager of one
     * branch could revoke a PIN across town — no forged request needed, just a
     * scroll. Mirrors HR > Employees, which has always scoped both its list and
     * its writes this way.
     */
    private function employee(int $id): Employee
    {
        $employee = Employee::findOrFail($id);

        abort_unless(
            in_array((int) $employee->outlet_id, $this->accessibleOutletIds(), true),
            403,
            'You do not have access to this employee.'
        );

        return $employee;
    }
}
