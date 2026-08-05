<?php

namespace App\Livewire\Hr;

use App\Models\CertificationType;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\Outlet;
use App\Models\Section;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Full-page add / edit form for an employee.
 *
 * Lives on its own route rather than in a modal on the list — the form is tall
 * enough (compensation, food handler, typhoid, halal blocks) that a dialog got
 * clipped on small laptops, the same reason the OT screen's employee modal was
 * retired. One component serves both create and edit; $employeeId is the switch.
 */
class EmployeeForm extends Component
{
    public ?int   $employeeId       = null;
    public ?int   $f_outlet_id      = null;
    public ?int   $f_section_id     = null;
    public string $f_staff_id       = '';
    public string $f_name           = '';
    public string $f_designation    = '';
    public string $f_email          = '';
    public string $f_phone_code     = '';
    public string $f_phone          = '';
    public string $f_join_date      = '';
    public string $f_employment_status      = '';
    public string $f_employment_status_date = '';
    public string $f_outsourcing_provider   = 'experiva'; // 'experiva' | 'others'
    public string $f_outsourcing_company    = '';
    public bool   $f_food_handler_certified = false;
    public string $f_food_handler_cert_no   = '';
    public string $f_food_handler_expired_on = '';
    public bool   $f_typhoid_card   = false;
    public string $f_typhoid_valid_from = '';
    public string $f_typhoid_expired_on = '';
    public bool   $f_halal_training       = false;
    public string $f_halal_training_date  = '';
    public string $f_halal_training_expired_on = '';
    public string $f_break_minutes     = '';

    /**
     * Catalogue certifications recorded against this employee.
     *
     * One row per course: ['type_id', 'reference_no', 'issued_on', 'expires_on'].
     * Kept as a plain array rather than models so an unsaved row costs nothing
     * and Cancel leaves no orphan behind.
     *
     * @var array<int, array<string, string>>
     */
    public array $f_certifications = [];
    public string $f_service_points    = '';
    public string $f_basic_salary      = '';
    public string $f_pay_type          = '';
    public bool   $f_is_active      = true;

    public function mount(?int $id = null): void
    {
        $this->f_phone_code = $this->defaultPhoneCode();

        if (! $id) {
            // Default new employees to the user's active outlet.
            $this->f_outlet_id = Auth::user()?->activeOutletId();
            return;
        }

        $emp = Employee::findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            abort(403, 'You do not have access to this employee.');
        }

        $this->employeeId      = $emp->id;
        $this->f_outlet_id     = $emp->outlet_id;
        $this->f_section_id    = $emp->section_id;
        $this->f_staff_id      = $emp->staff_id ?? '';
        $this->f_name          = $emp->name;
        $this->f_designation   = $emp->designation ?? '';
        $this->f_email         = $emp->email ?? '';
        [$this->f_phone_code, $this->f_phone] = $this->splitPhone($emp->phone);
        $this->f_join_date     = $emp->join_date?->format('Y-m-d') ?? '';
        $this->f_employment_status      = $emp->employment_status ?? '';
        $this->f_employment_status_date = $emp->employment_status_date?->format('Y-m-d') ?? '';
        $this->f_outsourcing_provider   = ($emp->outsourcing_company && strcasecmp($emp->outsourcing_company, 'Experiva') !== 0) ? 'others' : 'experiva';
        $this->f_outsourcing_company    = $this->f_outsourcing_provider === 'others' ? ($emp->outsourcing_company ?? '') : '';
        $this->f_food_handler_certified = (bool) $emp->food_handler_certified;
        $this->f_food_handler_cert_no   = $emp->food_handler_cert_no ?? '';
        $this->f_food_handler_expired_on = $emp->food_handler_expired_on?->format('Y-m-d') ?? '';
        $this->f_typhoid_card  = (bool) $emp->typhoid_card;
        $this->f_typhoid_valid_from = $emp->typhoid_valid_from?->format('Y-m-d') ?? '';
        $this->f_typhoid_expired_on = $emp->typhoid_expired_on?->format('Y-m-d') ?? '';
        $this->f_halal_training      = (bool) $emp->halal_training;
        $this->f_halal_training_date = $emp->halal_training_date?->format('Y-m-d') ?? '';
        $this->f_halal_training_expired_on = $emp->halal_training_expired_on?->format('Y-m-d') ?? '';
        $this->f_break_minutes = $emp->break_minutes !== null ? (string) $emp->break_minutes : '';
        $this->f_certifications = $emp->certifications()
            ->orderBy('certification_type_id')
            ->get()
            ->map(fn ($c) => [
                'type_id'      => (string) $c->certification_type_id,
                'reference_no' => $c->reference_no ?? '',
                'issued_on'    => $c->issued_on?->format('Y-m-d') ?? '',
                'expires_on'   => $c->expires_on?->format('Y-m-d') ?? '',
            ])
            ->all();
        // Pay fields are only hydrated for permitted users — otherwise they'd
        // ride along in the Livewire payload even with the inputs hidden.
        if ($this->canViewPay()) {
            $this->f_service_points = $emp->service_points_entitlement !== null
                ? number_format((float) $emp->service_points_entitlement, 2, '.', '')
                : '';
            $this->f_basic_salary = $emp->basic_salary !== null
                ? number_format((float) $emp->basic_salary, 2, '.', '')
                : '';
            $this->f_pay_type = $emp->pay_type ?? '';
        }
        $this->f_is_active = (bool) $emp->is_active;
    }

    /**
     * Outlet IDs this user is allowed to see — drives the outlet picker and
     * guards both the edit target and the saved outlet, so a user with limited
     * outlet access can't read or write employees outside their scope.
     */
    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    /**
     * Salary and service point entitlement are pay-sensitive: users without
     * hr.compensation never see them, and any value they submit is ignored.
     */
    protected function canViewPay(): bool
    {
        return Employee::canViewPay(Auth::user());
    }

    protected function rules(): array
    {
        $accessible = $this->accessibleOutletIds();
        return [
            'f_outlet_id'      => [
                'required', 'integer',
                \Illuminate\Validation\Rule::in($accessible),
            ],
            'f_section_id'  => 'nullable|integer|exists:sections,id',
            'f_staff_id'       => 'nullable|string|max:100',
            'f_name'           => 'required|string|max:255',
            'f_designation'    => 'nullable|string|max:255',
            'f_email'          => 'nullable|email|max:255',
            'f_phone_code'     => 'nullable|in:' . implode(',', array_values(Employee::PHONE_COUNTRY_CODES)),
            'f_phone'          => 'nullable|string|max:50',
            'f_join_date'      => 'nullable|date',
            'f_employment_status' => 'nullable|in:' . implode(',', array_keys(Employee::EMPLOYMENT_STATUSES)),
            'f_employment_status_date' => array_key_exists($this->f_employment_status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)
                ? 'required|date'
                : 'nullable|date',
            'f_outsourcing_provider' => 'in:experiva,others',
            'f_outsourcing_company'  => ($this->f_employment_status === 'outsourcing' && $this->f_outsourcing_provider === 'others')
                ? 'required|string|max:100'
                : 'nullable|string|max:100',
            'f_food_handler_certified' => 'boolean',
            'f_food_handler_cert_no'   => 'nullable|string|max:100',
            'f_food_handler_expired_on' => 'nullable|date',
            'f_typhoid_card'   => 'boolean',
            'f_typhoid_valid_from' => 'nullable|date',
            'f_typhoid_expired_on' => array_filter([
                'nullable', 'date',
                $this->f_typhoid_valid_from ? 'after:f_typhoid_valid_from' : null,
            ]),
            'f_halal_training'      => 'boolean',
            'f_halal_training_date' => 'nullable|date',
            'f_halal_training_expired_on' => array_filter([
                'nullable', 'date',
                $this->f_halal_training_date ? 'after:f_halal_training_date' : null,
            ]),
            // A blank means "use the roster's". 0 is a real answer (no paid
            // break), so it must stay distinguishable from blank.
            'f_break_minutes'       => 'nullable|integer|min:0|max:1440',
            'f_certifications'                 => 'array|max:50',
            'f_certifications.*.type_id'       => [
                'required',
                // The table holds one row per (employee, course), so a repeated
                // pick would fail at the database and lose the whole save.
                'distinct',
                \Illuminate\Validation\Rule::exists('certification_types', 'id')
                    ->where('company_id', Auth::user()->company_id),
            ],
            'f_certifications.*.reference_no'  => 'nullable|string|max:100',
            'f_certifications.*.issued_on'     => 'nullable|date',
            'f_certifications.*.expires_on'    => 'nullable|date',
            'f_service_points'      => 'nullable|numeric|min:0|max:999999.99',
            'f_basic_salary'        => 'nullable|numeric|min:0|max:9999999999.99',
            'f_pay_type'            => 'nullable|in:' . implode(',', array_keys(Employee::PAY_TYPES)),
            'f_is_active'      => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'f_outlet_id.in' => 'You do not have access to the selected outlet.',
            'f_typhoid_expired_on.after' => 'The Expired On date must be after the Valid From date.',
            'f_employment_status_date.required' => 'Please set the date for this employment status.',
            'f_outsourcing_company.required'    => 'Please enter the outsourcing company name.',
        ];
    }

    /** Company default dial code (from default_tax_country), MY fallback. */
    private function defaultPhoneCode(): string
    {
        $iso = strtoupper((string) Auth::user()?->company?->default_tax_country) ?: 'MY';

        return Employee::PHONE_COUNTRY_CODES[$iso] ?? '+60';
    }

    /**
     * Split a stored phone into [dial code, local part] by longest-prefix
     * match against the known codes; unknown formats keep the whole value in
     * the local part with the company default code selected.
     */
    private function splitPhone(?string $phone): array
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return [$this->defaultPhoneCode(), ''];
        }

        $codes = array_values(Employee::PHONE_COUNTRY_CODES);
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($codes as $code) {
            if (str_starts_with($phone, $code)) {
                return [$code, ltrim(substr($phone, strlen($code)))];
            }
        }

        return [$this->defaultPhoneCode(), $phone];
    }

    public function addCertification(): void
    {
        $this->f_certifications[] = [
            'type_id' => '', 'reference_no' => '', 'issued_on' => '', 'expires_on' => '',
        ];
    }

    public function removeCertification(int $index): void
    {
        unset($this->f_certifications[$index]);
        // Re-index, or Livewire renders the array as an object and the
        // remaining rows lose their wire:model bindings.
        $this->f_certifications = array_values($this->f_certifications);
    }

    /** Catalogue courses still available to add, given what is already listed. */
    public function availableCertificationTypes(): \Illuminate\Support\Collection
    {
        $taken = array_filter(array_column($this->f_certifications, 'type_id'));

        return CertificationType::active()->ordered()->get()
            ->reject(fn ($t) => in_array((string) $t->id, $taken, true));
    }

    public function save(): void
    {
        $this->validate();
        $user = Auth::user();

        $data = [
            'company_id'    => $user->company_id,
            'outlet_id'     => $this->f_outlet_id,
            'section_id' => $this->f_section_id ?: null,
            'staff_id'      => $this->f_staff_id ?: null,
            'name'          => $this->f_name,
            'designation'   => $this->f_designation ?: null,
            'email'         => $this->f_email ?: null,
            // Full number stored with its dial code, e.g. "+60 123456789".
            'phone'         => trim($this->f_phone)
                ? trim(($this->f_phone_code ?: $this->defaultPhoneCode()) . ' ' . trim($this->f_phone))
                : null,
            'join_date'     => $this->f_join_date ?: null,
            'employment_status' => $this->f_employment_status ?: null,
            // Date applies to probation/confirmed/extension; company to outsourcing.
            'employment_status_date' => array_key_exists($this->f_employment_status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)
                ? ($this->f_employment_status_date ?: null)
                : null,
            'outsourcing_company' => $this->f_employment_status === 'outsourcing'
                ? ($this->f_outsourcing_provider === 'others' ? ($this->f_outsourcing_company ?: null) : 'Experiva')
                : null,
            'food_handler_certified' => $this->f_food_handler_certified,
            // Cert number only applies while the certified box is ticked —
            // unticking clears it, same as the typhoid validity dates.
            'food_handler_cert_no'   => $this->f_food_handler_certified ? ($this->f_food_handler_cert_no ?: null) : null,
            'food_handler_expired_on' => $this->f_food_handler_certified ? ($this->f_food_handler_expired_on ?: null) : null,
            'typhoid_card'  => $this->f_typhoid_card,
            // Validity dates only apply while the card box is ticked — unticking
            // clears them so a "No" employee can't carry stale validity info.
            'typhoid_valid_from' => $this->f_typhoid_card ? ($this->f_typhoid_valid_from ?: null) : null,
            'typhoid_expired_on' => $this->f_typhoid_card ? ($this->f_typhoid_expired_on ?: null) : null,
            'halal_training'      => $this->f_halal_training,
            'halal_training_date' => $this->f_halal_training ? ($this->f_halal_training_date ?: null) : null,
            'halal_training_expired_on' => $this->f_halal_training ? ($this->f_halal_training_expired_on ?: null) : null,
            // A past leaving date wins over the Active toggle: nobody thinks to
            // untick it when recording a resignation, and while the row stays
            // active the person keeps appearing on every future attendance
            // grid and roster. Future-dated resignations stay active until the
            // day arrives — hr:apply-resignations flips those overnight.
            'is_active'     => $this->f_is_active && ! Employee::resignationTookEffect(
                $this->f_employment_status ?: null,
                array_key_exists($this->f_employment_status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)
                    ? ($this->f_employment_status_date ?: null)
                    : null,
            ),
            // Blank means "use the roster's allowance"; 0 is a real answer.
            'break_minutes' => $this->f_break_minutes !== '' ? (int) $this->f_break_minutes : null,
        ];

        // Pay fields are omitted entirely for users without hr.compensation, so
        // an ordinary HR user editing an employee leaves salary and service
        // points untouched rather than blanking values they can't even see.
        if ($this->canViewPay()) {
            $data['service_points_entitlement'] = $this->f_service_points !== ''
                ? round((float) $this->f_service_points, 2)
                : null;
            $data['basic_salary'] = $this->f_basic_salary !== ''
                ? round((float) $this->f_basic_salary, 2)
                : null;
            // Pay type is meaningless without an amount.
            $data['pay_type'] = $this->f_basic_salary !== ''
                ? ($this->f_pay_type ?: 'monthly')
                : null;
        }

        if ($this->employeeId) {
            $emp = Employee::findOrFail($this->employeeId);
            if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
                abort(403, 'You do not have access to this employee.');
            }
            $emp->update($data);
            session()->flash('success', 'Employee updated.');
        } else {
            $emp = Employee::create($data);
            session()->flash('success', 'Employee added.');
        }

        $this->syncCertifications($emp);

        $this->redirectRoute('hr.employees');
    }

    /**
     * Bring the employee's catalogue certifications in line with the form.
     *
     * Rows the form no longer carries are deleted, which is the only way to
     * correct a course recorded against the wrong person. Existing rows are
     * updated in place rather than deleted and recreated, so the record keeps
     * its created_at — when a certificate was first logged is the sort of
     * thing an audit asks about.
     */
    private function syncCertifications(Employee $employee): void
    {
        $companyId = $employee->company_id;
        $keptTypeIds = [];

        foreach ($this->f_certifications as $row) {
            $typeId = (int) ($row['type_id'] ?? 0);
            if (! $typeId) continue;

            $keptTypeIds[] = $typeId;

            EmployeeCertification::updateOrCreate(
                ['employee_id' => $employee->id, 'certification_type_id' => $typeId],
                [
                    'company_id'   => $companyId,
                    'reference_no' => ($row['reference_no'] ?? '') ?: null,
                    'issued_on'    => ($row['issued_on'] ?? '') ?: null,
                    'expires_on'   => ($row['expires_on'] ?? '') ?: null,
                ]
            );
        }

        EmployeeCertification::where('employee_id', $employee->id)
            ->when($keptTypeIds, fn ($q) => $q->whereNotIn('certification_type_id', $keptTypeIds))
            ->delete();
    }

    public function render()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        // Only offer outlets the current user can actually access.
        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $this->accessibleOutletIds())
            ->orderBy('name')
            ->get();

        $sections   = Section::active()->ordered()->get();
        $canViewPay = $this->canViewPay();
        // The full catalogue for naming a chosen row, plus what is still free
        // to add — a course already listed must not be offered twice.
        // Hides the expiry inputs for documents this company treats as one-off.
        $complianceSettings    = \App\Models\ComplianceSetting::forCompany($companyId);
        $certificationTypes    = CertificationType::ordered()->get()->keyBy('id');
        $availableCertifications = $this->availableCertificationTypes();

        return view('livewire.hr.employee-form', compact(
            'outlets', 'sections', 'canViewPay', 'certificationTypes', 'availableCertifications', 'complianceSettings'
        ))
            ->layout('layouts.app', ['title' => $this->employeeId ? 'Edit Employee' : 'Add Employee']);
    }
}
