<?php

namespace App\Livewire\Hr;

use App\Models\Bank;
use App\Models\CertificationType;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\EmployeeDocument;
use App\Models\HrOption;
use App\Models\Outlet;
use App\Models\Section;
use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

    public ?int   $employeeId       = null;
    public ?int   $f_outlet_id      = null;
    public ?int   $f_section_id     = null;
    /** Superior — who this employee's leave goes to. */
    public ?int   $f_reports_to_id  = null;
    public string $f_staff_id       = '';
    public string $f_name           = '';
    public string $f_designation    = '';
    public string $f_email          = '';
    public string $f_phone_code     = '';
    public string $f_phone          = '';
    public string $f_join_date      = '';
    public string $f_ic_number      = '';
    public string $f_date_of_birth  = '';

    /*
     * Particulars, each picked from a list the company manages in
     * Settings › Employee Particulars. Mapped rather than handled one by one:
     * the type key gives the list, the list gives the employees column, and a
     * seventh particular is one more line here.
     */
    public string $f_gender          = '';
    public string $f_nationality     = '';
    public string $f_race            = '';
    public string $f_religion        = '';
    public string $f_marital_status  = '';
    public string $f_education_level = '';
    public string $f_emergency_contact_relationship = '';

    /** @var array<string, string> HrOption type => the property holding it. */
    public const PARTICULARS = [
        'gender'          => 'f_gender',
        'nationality'     => 'f_nationality',
        'race'            => 'f_race',
        'religion'        => 'f_religion',
        'marital_status'  => 'f_marital_status',
        'education_level' => 'f_education_level',
        'relationship'    => 'f_emergency_contact_relationship',
    ];

    /**
     * What each particular was when the record loaded.
     *
     * Same rule as the bank picker: a value since retired from its list stays
     * selectable, so an unrelated edit cannot quietly blank somebody's
     * nationality because an admin tidied the list.
     *
     * @var array<string, string>
     */
    public array $originalParticulars = [];

    /*
     * Emergency contact. On the employee row rather than a related record —
     * a manager looking this up at 2am should not depend on a join.
     */
    public string $f_emergency_contact_name      = '';
    public string $f_emergency_contact_phone     = '';
    public string $f_emergency_contact_phone_alt = '';
    public string $f_emergency_contact_address   = '';

    /** Staff photograph. Held on the private disk — see the migration. */
    public $photo = null;
    public ?string $photoPath = null;

    /* Document upload. One at a time, filed under a type. */
    public $docFile = null;
    public string $docType  = 'application_form';
    public string $docLabel = '';
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
    public string $f_daily_working_hours = '';

    /**
     * Whether this person may clock in on their own phone.
     *
     * A STRING with three values, not a bool, because the useful state is the
     * third one: '' means "whatever the outlet says", which is what nearly
     * everybody should be. Storing it as a real choice would freeze each
     * employee against today's outlet policy, so moving an outlet onto its
     * kiosk would silently leave every existing member of staff exempt from it.
     */
    public string $f_allow_byod = '';

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
    // Where the salary is paid. Pay-gated with the amount: an account number
    // deserves the same protection as the figure paid into it.
    public string $f_bank_name         = '';
    public string $f_bank_account_no   = '';

    /**
     * The bank name this employee was loaded with.
     *
     * Kept so a name that is no longer in the picker — typed in before it
     * existed, or since removed from Settings › Banks — stays selectable and
     * survives an unrelated edit. Blanking somebody's bank because an admin
     * tidied the list is how a salary goes unpaid.
     */
    public string $originalBankName    = '';

    /*
     * Statutory profile, folded in from what used to be a modal on the
     * Compensation screen. It belongs with the employee record: these are
     * facts about the person, not a transaction, and splitting them across two
     * screens is what made them hard to find in the first place.
     */
    public string $s_epf_number   = '';
    public string $s_socso_number = '';
    public string $s_tax_number   = '';
    public bool   $s_is_malaysian = true;
    public bool   $s_epf          = true;
    public bool   $s_socso        = true;
    public bool   $s_eis          = true;
    public bool   $s_hrdf         = true;
    public bool   $s_pcb          = true;
    public string $s_epf_override = '';
    public string $s_pcb_category = 'single';
    public string $s_children     = '0';
    public string $s_zakat        = '0';
    public string $s_other_relief = '0';
    public bool   $f_is_active      = true;

    public function mount(?int $id = null): void
    {
        $this->f_phone_code = $this->defaultPhoneCode();

        // A picker nobody has ever opened the settings screen for is empty, and
        // an empty picker cannot record anything.
        HrOption::seedDefaults(Auth::user()->company_id);
        if ($this->canViewPay()) {
            Bank::seedDefaults(Auth::user()->company_id);
        }

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
        $this->f_reports_to_id = $emp->reports_to_id;
        $this->f_staff_id      = $emp->staff_id ?? '';
        $this->f_name          = $emp->name;
        $this->f_designation   = $emp->designation ?? '';
        $this->f_email         = $emp->email ?? '';
        [$this->f_phone_code, $this->f_phone] = $this->splitPhone($emp->phone);
        $this->f_join_date     = $emp->join_date?->format('Y-m-d') ?? '';
        $this->f_ic_number     = $emp->ic_number ?? '';
        $this->f_date_of_birth = $emp->date_of_birth?->format('Y-m-d') ?? '';

        foreach (self::PARTICULARS as $type => $prop) {
            $this->$prop = $emp->{HrOption::columnFor($type)} ?? '';
            $this->originalParticulars[$type] = $this->$prop;
        }

        $this->f_emergency_contact_name      = $emp->emergency_contact_name ?? '';
        $this->f_emergency_contact_phone     = $emp->emergency_contact_phone ?? '';
        $this->f_emergency_contact_phone_alt = $emp->emergency_contact_phone_alt ?? '';
        $this->f_emergency_contact_address   = $emp->emergency_contact_address ?? '';
        $this->photoPath = $emp->photo_path;
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
        $this->f_daily_working_hours = $emp->daily_working_hours !== null ? (string) (float) $emp->daily_working_hours : '';
        $this->f_allow_byod = $emp->allow_byod === null ? '' : ($emp->allow_byod ? 'yes' : 'no');
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
            $this->f_bank_name       = $emp->bank_name ?? '';
            $this->originalBankName  = $this->f_bank_name;
            $this->f_bank_account_no = $emp->bank_account_no ?? '';

            $p = \App\Models\EmployeeStatutoryProfile::forEmployee($emp);
            $this->s_epf_number   = $p->epf_number ?? '';
            $this->s_socso_number = $p->socso_number ?? '';
            $this->s_tax_number   = $p->income_tax_number ?? '';
            $this->s_is_malaysian = (bool) $p->is_malaysian;
            $this->s_epf          = (bool) $p->epf_enabled;
            $this->s_socso        = (bool) $p->socso_enabled;
            $this->s_eis          = (bool) $p->eis_enabled;
            $this->s_hrdf         = (bool) $p->hrdf_enabled;
            $this->s_pcb          = (bool) $p->pcb_enabled;
            $this->s_epf_override = $p->epf_employee_rate_override !== null
                ? (string) (float) $p->epf_employee_rate_override : '';
            $this->s_pcb_category = $p->pcb_category ?: 'single';
            $this->s_children     = (string) $p->children;
            $this->s_zakat        = (string) (float) $p->monthly_zakat;
            $this->s_other_relief = (string) (float) $p->annual_other_relief;
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

    /**
     * The banks this employee may be paid into: the company's active list, plus
     * the name already on the record if that has since been retired or was
     * typed in before the picker existed.
     *
     * The stale one is appended rather than dropped so an ordinary edit — a
     * phone number, a section — cannot quietly wipe somebody's bank.
     *
     * @return \Illuminate\Support\Collection<int, Bank>
     */
    public function bankOptions(): \Illuminate\Support\Collection
    {
        $banks = Bank::active()->ordered()->get();

        if ($this->originalBankName !== '' && ! $banks->contains('name', $this->originalBankName)) {
            $banks->push(new Bank([
                'name' => $this->originalBankName,
                'bic'  => Bank::bicFor($this->originalBankName),
            ]));
        }

        return $banks;
    }

    /**
     * The values one particular may be set to: its list, plus whatever this
     * record already held. @see bankOptions() for why the stale one stays.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function particularOptions(string $type): \Illuminate\Support\Collection
    {
        return HrOption::namesFor($type, $this->originalParticulars[$type] ?? null);
    }

    protected function rules(): array
    {
        $accessible = $this->accessibleOutletIds();

        $particulars = [];
        foreach (self::PARTICULARS as $type => $prop) {
            $particulars[$prop] = [
                'nullable', 'string', 'max:60',
                \Illuminate\Validation\Rule::in($this->particularOptions($type)->all()),
            ];
        }

        return $particulars + [
            'f_outlet_id'      => [
                'required', 'integer',
                \Illuminate\Validation\Rule::in($accessible),
            ],
            'f_section_id'  => 'nullable|integer|exists:sections,id',
            'f_reports_to_id' => 'nullable|integer|exists:employees,id',
            'f_staff_id'       => 'nullable|string|max:100',
            'f_name'           => 'required|string|max:255',
            'f_designation'    => 'nullable|string|max:255',
            'f_email'          => 'nullable|email|max:255',
            'f_phone_code'     => 'nullable|in:' . implode(',', array_values(Employee::PHONE_COUNTRY_CODES)),
            'f_phone'          => 'nullable|string|max:50',
            'f_join_date'      => 'nullable|date',
            'f_ic_number'      => 'nullable|string|max:20',
            'f_date_of_birth'  => 'nullable|date|before:today',
            'f_emergency_contact_name'      => 'nullable|string|max:120',
            'f_emergency_contact_phone'     => 'nullable|string|max:50',
            'f_emergency_contact_phone_alt' => 'nullable|string|max:50',
            'f_emergency_contact_address'   => 'nullable|string|max:255',
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
            'f_daily_working_hours' => 'nullable|numeric|min:1|max:24',
            // '' is the inherit-from-outlet case and the default.
            'f_allow_byod'          => 'nullable|in:,yes,no',
            // Picked from the company's bank list, plus whatever this record
            // already held — see $originalBankName.
            'f_bank_name'           => [
                'nullable', 'string', 'max:60',
                \Illuminate\Validation\Rule::in($this->bankOptions()->pluck('name')->all()),
            ],
            'f_bank_account_no'     => 'nullable|string|max:40',
            's_epf_number'   => 'nullable|string|max:30',
            's_socso_number' => 'nullable|string|max:30',
            's_tax_number'   => 'nullable|string|max:30',
            's_epf_override' => 'nullable|numeric|min:0|max:100',
            's_pcb_category' => 'required|in:' . implode(',', array_keys(\App\Models\StatutorySetting::PCB_CATEGORIES)),
            's_children'     => 'required|integer|min:0|max:50',
            's_zakat'        => 'required|numeric|min:0|max:1000000',
            's_other_relief' => 'required|numeric|min:0|max:1000000',
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

    /**
     * Drop the photograph on record.
     *
     * Applied immediately rather than on save: "remove" that only takes effect
     * if you then remember to press Save is how a photo somebody asked to have
     * taken down stays up.
     */
    public function removePhoto(): void
    {
        if (! $this->employeeId) {
            $this->photo = null;
            return;
        }

        $emp = $this->authorisedEmployee();
        $old = $emp->photo_path;
        $emp->update(['photo_path' => null]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        $this->photo     = null;
        $this->photoPath = null;
    }

    /**
     * File a scan against this employee.
     *
     * Uploads land immediately rather than waiting for Save. A document is not
     * a field of the form — it is a thing that either exists or does not — and
     * an upload silently discarded by Cancel is the sort of loss nobody thinks
     * to check for.
     */
    public function uploadDocument(): void
    {
        if (! $this->employeeId) {
            session()->flash('doc_error', 'Save the employee first — a document needs a record to hang off.');
            return;
        }

        $this->validate([
            // PDFs and photographs of paperwork, which is what a phone produces.
            'docFile'  => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,heic,heif',
            'docType'  => 'required|in:' . implode(',', array_keys(EmployeeDocument::TYPES)),
            'docLabel' => 'nullable|string|max:120',
        ], [
            'docFile.required' => 'Choose a file to upload.',
            'docFile.max'      => 'The file may not be larger than 10 MB.',
            'docFile.mimes'    => 'Upload a PDF or a photo of the document.',
        ]);

        $emp = $this->authorisedEmployee();

        $original = $this->docFile->getClientOriginalName();
        $size     = $this->docFile->getSize();
        $mime     = $this->docFile->getMimeType();

        // storeCompressed shrinks photographed paperwork and leaves PDFs alone.
        $path = ImageStorageService::storeCompressed(
            $this->docFile, 'employee-documents/' . $emp->company_id . '/' . $emp->id, 'local'
        );

        EmployeeDocument::create([
            'company_id'    => $emp->company_id,
            'employee_id'   => $emp->id,
            'type'          => $this->docType,
            'label'         => trim($this->docLabel) ?: null,
            'file_path'     => $path,
            'original_name' => $original,
            'mime_type'     => $mime,
            'size_bytes'    => Storage::disk('local')->size($path) ?: $size,
            'uploaded_by'   => Auth::id(),
        ]);

        $this->reset(['docFile', 'docLabel']);
        session()->flash('doc_success', 'Document uploaded.');
    }

    public function deleteDocument(int $id): void
    {
        $this->authorisedEmployee();

        // The model's deleted hook removes the file — see EmployeeDocument.
        EmployeeDocument::where('employee_id', $this->employeeId)->findOrFail($id)->delete();

        session()->flash('doc_success', 'Document removed.');
    }

    /** The employee being edited, refused unless this user may reach them. */
    private function authorisedEmployee(): Employee
    {
        $emp = Employee::findOrFail($this->employeeId);

        abort_unless(
            in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true),
            403,
            'You do not have access to this employee.'
        );

        return $emp;
    }

    public function save(): void
    {
        $this->validate();
        $user = Auth::user();

        $data = [
            'company_id'    => $user->company_id,
            'outlet_id'     => $this->f_outlet_id,
            'section_id' => $this->f_section_id ?: null,
            // Never themselves: a self-reference is a loop, and always a slip.
            'reports_to_id' => ($this->f_reports_to_id && $this->f_reports_to_id !== $this->employeeId)
                ? $this->f_reports_to_id : null,
            'staff_id'      => $this->f_staff_id ?: null,
            'name'          => $this->f_name,
            'designation'   => $this->f_designation ?: null,
            'email'         => $this->f_email ?: null,
            // Full number stored with its dial code, e.g. "+60 123456789".
            'phone'         => trim($this->f_phone)
                ? trim(($this->f_phone_code ?: $this->defaultPhoneCode()) . ' ' . trim($this->f_phone))
                : null,
            'join_date'     => $this->f_join_date ?: null,
            'ic_number'     => $this->f_ic_number ?: null,
            'date_of_birth' => $this->f_date_of_birth ?: null,
            'emergency_contact_name'      => $this->f_emergency_contact_name ?: null,
            'emergency_contact_phone'     => $this->f_emergency_contact_phone ?: null,
            'emergency_contact_phone_alt' => $this->f_emergency_contact_phone_alt ?: null,
            'emergency_contact_address'   => $this->f_emergency_contact_address ?: null,
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
            // Blank follows the company default rather than storing a copy.
            'daily_working_hours' => $this->f_daily_working_hours !== ''
                ? round((float) $this->f_daily_working_hours, 2) : null,
            // Each picked from its managed list — see PARTICULARS.
            ...collect(self::PARTICULARS)
                ->mapWithKeys(fn ($prop, $type) => [HrOption::columnFor($type) => $this->$prop ?: null])
                ->all(),
            // null inherits the outlet's punch mode. See canUseOwnDevice().
            'allow_byod' => match ($this->f_allow_byod) {
                'yes'   => true,
                'no'    => false,
                default => null,
            },
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
            $data['bank_name']       = $this->f_bank_name ?: null;
            $data['bank_account_no'] = $this->f_bank_account_no ?: null;
        }

        // Stored before the row is written so a failed save leaves no file
        // behind, and the one it replaces is only deleted once it has.
        $replacedPhoto = null;
        if ($this->photo) {
            $this->validate(['photo' => 'image|max:5120'], [
                'photo.image' => 'The photo must be an image.',
                'photo.max'   => 'The photo may not be larger than 5 MB.',
            ]);
            $replacedPhoto      = $this->photoPath;
            $data['photo_path'] = ImageStorageService::storeCompressed(
                $this->photo, 'employee-photos/' . $user->company_id, 'local'
            );
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

        if ($replacedPhoto) {
            Storage::disk('local')->delete($replacedPhoto);
        }

        $this->syncCertifications($emp);
        $this->syncStatutoryProfile($emp);

        $this->redirectRoute('hr.employees');
    }

    /**
     * Write the statutory profile alongside the employee.
     *
     * Only for users who may see pay: the fields are not hydrated for anyone
     * else, so writing them would blank a profile they cannot even read. That
     * is the same rule the salary fields follow, for the same reason.
     */
    private function syncStatutoryProfile(Employee $employee): void
    {
        if (! $this->canViewPay()) {
            return;
        }

        \App\Models\EmployeeStatutoryProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'company_id'        => $employee->company_id,
                'epf_number'        => $this->s_epf_number ?: null,
                'socso_number'      => $this->s_socso_number ?: null,
                'income_tax_number' => $this->s_tax_number ?: null,
                'is_malaysian'      => $this->s_is_malaysian,
                'epf_enabled'       => $this->s_epf,
                'socso_enabled'     => $this->s_socso,
                'eis_enabled'       => $this->s_eis,
                'hrdf_enabled'      => $this->s_hrdf,
                'pcb_enabled'       => $this->s_pcb,
                'epf_employee_rate_override' => $this->s_epf_override !== ''
                    ? round((float) $this->s_epf_override, 2) : null,
                'pcb_category'        => $this->s_pcb_category,
                'children'            => (int) $this->s_children,
                'monthly_zakat'       => round((float) $this->s_zakat, 2),
                'annual_other_relief' => round((float) $this->s_other_relief, 2),
            ]
        );
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
        $banks      = $canViewPay ? $this->bankOptions() : collect();

        // Keyed by type so the blade asks for the list by name rather than
        // knowing which property holds which particular.
        $particulars = collect(self::PARTICULARS)
            ->keys()
            ->mapWithKeys(fn ($type) => [$type => $this->particularOptions($type)]);

        $documents = $this->employeeId
            ? EmployeeDocument::where('employee_id', $this->employeeId)->get()->groupBy('type')
            : collect();

        // Who this person can report to. Excludes themselves and anyone who
        // already reports to them — a two-person cycle makes the chain
        // unwalkable and is the one that actually happens.
        $superiors = app(\App\Services\Hr\LeaveApprovers::class)->candidateSuperiors(
            $this->employeeId ? Employee::findOrFail($this->employeeId) : new Employee(['company_id' => Auth::user()->company_id])
        );
        // The full catalogue for naming a chosen row, plus what is still free
        // to add — a course already listed must not be offered twice.
        // Hides the expiry inputs for documents this company treats as one-off.
        $complianceSettings    = \App\Models\ComplianceSetting::forCompany($companyId);
        $certificationTypes    = CertificationType::ordered()->get()->keyBy('id');
        $availableCertifications = $this->availableCertificationTypes();

        return view('livewire.hr.employee-form', compact(
            'outlets', 'sections', 'canViewPay', 'banks', 'particulars', 'documents',
            'certificationTypes', 'availableCertifications', 'complianceSettings', 'superiors'
        ))
            ->layout('layouts.app', ['title' => $this->employeeId ? 'Edit Employee' : 'Add Employee']);
    }
}
