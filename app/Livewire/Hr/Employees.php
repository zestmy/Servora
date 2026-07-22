<?php

namespace App\Livewire\Hr;

use App\Models\Section;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Employees extends Component
{
    use WithFileUploads, WithPagination;

    // Filters
    public string $search           = '';
    public string $outletFilter     = '';
    public string $sectionFilter = '';
    public string $statusFilter     = 'active';
    public string $employmentStatusFilter = ''; // '' all | status key | 'none'

    // Add/edit modal
    public bool  $showForm          = false;
    public ?int  $editingId         = null;
    public ?int  $f_outlet_id       = null;
    public ?int  $f_section_id   = null;
    public string $f_staff_id       = '';
    public string $f_name           = '';
    public string $f_designation    = '';
    public string $f_email          = '';
    public string $f_phone          = '';
    public string $f_join_date      = '';
    public string $f_employment_status      = '';
    public string $f_employment_status_date = '';
    public string $f_outsourcing_provider   = 'experiva'; // 'experiva' | 'others'
    public string $f_outsourcing_company    = '';
    public bool   $f_food_handler_certified = false;
    public string $f_food_handler_cert_no   = '';
    public bool   $f_typhoid_card   = false;
    public string $f_typhoid_valid_from = '';
    public string $f_typhoid_expired_on = '';
    public bool   $f_is_active      = true;

    // CSV import modal
    public bool  $showImport   = false;
    public $csvFile            = null;
    public ?array $importResult = null;

    public function mount(): void
    {
        // Default the outlet filter to the user's active outlet so screens feel
        // consistent with the rest of Servora (they only see their current
        // outlet unless they explicitly opt into "All").
        if ($this->outletFilter === '') {
            $activeOutletId = Auth::user()?->activeOutletId();
            if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
        }
    }

    /**
     * Outlet IDs this user is allowed to see. Drives the list query, the
     * filter dropdown options, the form's outlet picker, and the CSV import
     * allow-list so a user with limited outlet access can't read / write
     * employees outside their scope.
     */
    protected function accessibleOutletIds(): array
    {
        $user = Auth::user();
        if ($user->canViewAllOutlets()) {
            return Outlet::where('company_id', $user->company_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        return $user->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();
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
            'f_phone'          => 'nullable|string|max:50',
            'f_join_date'      => 'nullable|date',
            'f_employment_status' => 'nullable|in:' . implode(',', array_keys(Employee::EMPLOYMENT_STATUSES)),
            'f_employment_status_date' => in_array($this->f_employment_status, ['probation', 'confirmed', 'extended_probation'], true)
                ? 'required|date'
                : 'nullable|date',
            'f_outsourcing_provider' => 'in:experiva,others',
            'f_outsourcing_company'  => ($this->f_employment_status === 'outsourcing' && $this->f_outsourcing_provider === 'others')
                ? 'required|string|max:100'
                : 'nullable|string|max:100',
            'f_food_handler_certified' => 'boolean',
            'f_food_handler_cert_no'   => 'nullable|string|max:100',
            'f_typhoid_card'   => 'boolean',
            'f_typhoid_valid_from' => 'nullable|date',
            'f_typhoid_expired_on' => array_filter([
                'nullable', 'date',
                $this->f_typhoid_valid_from ? 'after:f_typhoid_valid_from' : null,
            ]),
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

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingOutletFilter(): void   { $this->resetPage(); }
    public function updatingSectionFilter(): void  { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }
    public function updatingEmploymentStatusFilter(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        // Default new employees to the user's active outlet.
        $this->f_outlet_id = Auth::user()?->activeOutletId();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $emp = Employee::findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            session()->flash('error', 'You do not have access to this employee.');
            return;
        }
        $this->editingId       = $emp->id;
        $this->f_outlet_id     = $emp->outlet_id;
        $this->f_section_id    = $emp->section_id;
        $this->f_staff_id      = $emp->staff_id ?? '';
        $this->f_name          = $emp->name;
        $this->f_designation   = $emp->designation ?? '';
        $this->f_email         = $emp->email ?? '';
        $this->f_phone         = $emp->phone ?? '';
        $this->f_join_date     = $emp->join_date?->format('Y-m-d') ?? '';
        $this->f_employment_status      = $emp->employment_status ?? '';
        $this->f_employment_status_date = $emp->employment_status_date?->format('Y-m-d') ?? '';
        $this->f_outsourcing_provider   = ($emp->outsourcing_company && strcasecmp($emp->outsourcing_company, 'Experiva') !== 0) ? 'others' : 'experiva';
        $this->f_outsourcing_company    = $this->f_outsourcing_provider === 'others' ? ($emp->outsourcing_company ?? '') : '';
        $this->f_food_handler_certified = (bool) $emp->food_handler_certified;
        $this->f_food_handler_cert_no   = $emp->food_handler_cert_no ?? '';
        $this->f_typhoid_card  = (bool) $emp->typhoid_card;
        $this->f_typhoid_valid_from = $emp->typhoid_valid_from?->format('Y-m-d') ?? '';
        $this->f_typhoid_expired_on = $emp->typhoid_expired_on?->format('Y-m-d') ?? '';
        $this->f_is_active     = (bool) $emp->is_active;
        $this->showForm        = true;
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
            'phone'         => $this->f_phone ?: null,
            'join_date'     => $this->f_join_date ?: null,
            'employment_status' => $this->f_employment_status ?: null,
            // Date applies to probation/confirmed/extension; company to outsourcing.
            'employment_status_date' => in_array($this->f_employment_status, ['probation', 'confirmed', 'extended_probation'], true)
                ? ($this->f_employment_status_date ?: null)
                : null,
            'outsourcing_company' => $this->f_employment_status === 'outsourcing'
                ? ($this->f_outsourcing_provider === 'others' ? ($this->f_outsourcing_company ?: null) : 'Experiva')
                : null,
            'food_handler_certified' => $this->f_food_handler_certified,
            // Cert number only applies while the certified box is ticked —
            // unticking clears it, same as the typhoid validity dates.
            'food_handler_cert_no'   => $this->f_food_handler_certified ? ($this->f_food_handler_cert_no ?: null) : null,
            'typhoid_card'  => $this->f_typhoid_card,
            // Validity dates only apply while the card box is ticked — unticking
            // clears them so a "No" employee can't carry stale validity info.
            'typhoid_valid_from' => $this->f_typhoid_card ? ($this->f_typhoid_valid_from ?: null) : null,
            'typhoid_expired_on' => $this->f_typhoid_card ? ($this->f_typhoid_expired_on ?: null) : null,
            'is_active'     => $this->f_is_active,
        ];

        if ($this->editingId) {
            Employee::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Employee updated.');
        } else {
            Employee::create($data);
            session()->flash('success', 'Employee added.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $emp = Employee::findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            session()->flash('error', 'You do not have access to this employee.');
            return;
        }
        $emp->update(['is_active' => ! $emp->is_active]);
    }

    public function delete(int $id): void
    {
        $emp = Employee::findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            session()->flash('error', 'You do not have access to this employee.');
            return;
        }
        $emp->delete();
        session()->flash('success', 'Employee deleted.');
    }

    protected function resetForm(): void
    {
        $this->editingId       = null;
        $this->f_outlet_id     = null;
        $this->f_section_id = null;
        $this->f_staff_id      = '';
        $this->f_name          = '';
        $this->f_designation   = '';
        $this->f_email         = '';
        $this->f_phone         = '';
        $this->f_join_date     = '';
        $this->f_employment_status      = '';
        $this->f_employment_status_date = '';
        $this->f_outsourcing_provider   = 'experiva';
        $this->f_outsourcing_company    = '';
        $this->f_food_handler_certified = false;
        $this->f_food_handler_cert_no   = '';
        $this->f_typhoid_card  = false;
        $this->f_typhoid_valid_from = '';
        $this->f_typhoid_expired_on = '';
        $this->f_is_active     = true;
    }

    // ── CSV import ─────────────────────────────────────────────────────────

    public function openImport(): void
    {
        $this->reset(['csvFile', 'importResult']);
        $this->showImport = true;
    }

    public function processImport(): void
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $user       = Auth::user();
        $companyId  = $user->company_id;
        $accessible = $this->accessibleOutletIds();

        // Build outlet + section lookups for this company (name lowercased → id).
        // Outlet map is limited to the user's accessible outlets so a row for
        // an outlet they can't see is rejected rather than silently written.
        $outletMap = Outlet::where('company_id', $companyId)
            ->whereIn('id', $accessible)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        $sectionMap = Section::where('company_id', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        // Read file as text first so we can strip BOM, normalise line endings,
        // and auto-detect the delimiter (Excel on some locales emits ';' or '\t').
        $raw = file_get_contents($this->csvFile->getRealPath()) ?: '';
        if ($raw === '') {
            session()->flash('error', 'Could not read CSV file.');
            return;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);    // strip UTF-8 BOM
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $firstLine = strtok($raw, "\n") ?: '';
        $delim = ',';
        foreach ([",", ";", "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delim)) {
                $delim = $candidate;
            }
        }

        $tmp = tmpfile();
        fwrite($tmp, $raw);
        rewind($tmp);

        $headers = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 0;

        // Normalise header tokens so small wording differences still map.
        $aliasMap = [
            'outlet'          => 'outlet',
            'branch'          => 'outlet',
            'location'        => 'outlet',
            'employee name'   => 'name',
            'name'            => 'name',
            'full name'       => 'name',
            'designation'     => 'designation',
            'position'        => 'designation',
            'job title'       => 'designation',
            'role'            => 'designation',
            'section'         => 'section',
            'department'      => 'section',
            'dept'            => 'section',
            'staff id'        => 'staff_id',
            'staff no'        => 'staff_id',
            'staff no.'       => 'staff_id',
            'employee id'     => 'staff_id',
            'emp id'          => 'staff_id',
            'e-mail'          => 'email',
            'email'           => 'email',
            'email address'   => 'email',
            'phone number'    => 'phone',
            'phone'           => 'phone',
            'phone no'        => 'phone',
            'mobile'          => 'phone',
            'mobile number'   => 'phone',
            'contact'         => 'phone',
            'contact number'  => 'phone',
            'join date'       => 'join_date',
            'joining date'    => 'join_date',
            'date joined'     => 'join_date',
            'joined'          => 'join_date',
            'employment status'      => 'employment_status',
            'employment'             => 'employment_status',
            'employment status date' => 'employment_status_date',
            'status date'            => 'employment_status_date',
            'probation until'        => 'employment_status_date',
            'confirmed on'           => 'employment_status_date',
            'outsourcing company'    => 'outsourcing_company',
            'outsourcing provider'   => 'outsourcing_company',
            'food handler'    => 'food_handler_certified',
            'food handler certified' => 'food_handler_certified',
            'food handler certification' => 'food_handler_certified',
            'food handler cert' => 'food_handler_certified',
            'food handler cert no'      => 'food_handler_cert_no',
            'food handler cert no.'     => 'food_handler_cert_no',
            'food handler cert number'  => 'food_handler_cert_no',
            'food handler certificate no'     => 'food_handler_cert_no',
            'food handler certificate number' => 'food_handler_cert_no',
            'food handler serial no'     => 'food_handler_cert_no',
            'food handler serial number' => 'food_handler_cert_no',
            'typhoid'         => 'typhoid_card',
            'typhoid card'    => 'typhoid_card',
            'typhoid jab'     => 'typhoid_card',
            'typhoid valid from'  => 'typhoid_valid_from',
            'typhoid valid'       => 'typhoid_valid_from',
            'typhoid expired on'  => 'typhoid_expired_on',
            'typhoid expiry'      => 'typhoid_expired_on',
            'typhoid expiry date' => 'typhoid_expired_on',
            'typhoid expired'     => 'typhoid_expired_on',
        ];

        $parseBool = fn (string $v): bool => in_array(
            strtolower(trim($v)), ['yes', 'y', '1', 'true', 'certified'], true
        );

        while (($row = fgetcsv($tmp, 0, $delim)) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $unmapped = [];
                $headers = array_map(function ($h) use ($aliasMap, &$unmapped) {
                    $key = strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"));
                    $mapped = $aliasMap[$key] ?? null;
                    if (! $mapped && $key !== '') $unmapped[] = $h;
                    return $mapped;
                }, $row);

                if (! in_array('outlet', $headers, true) || ! in_array('name', $headers, true)) {
                    fclose($tmp);
                    $this->importResult = [
                        'created' => 0, 'updated' => 0, 'skipped' => 0,
                        'errors'  => [
                            'Required columns Outlet and Employee Name were not found.',
                            'Headers detected: ' . implode(', ', array_map(fn ($h) => '"' . $h . '"', $row)),
                            'Expected: Outlet, Employee Name, Designation, Section, Staff ID, E-mail, Phone Number',
                        ],
                    ];
                    $this->csvFile = null;
                    return;
                }
                continue;
            }
            // Skip empty rows
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $data = [];
            foreach ($headers as $i => $key) {
                if (! $key) continue;
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }

            $name = $data['name'] ?? '';
            if ($name === '') {
                $errors[] = "Row $rowNum: missing name";
                $skipped++;
                continue;
            }

            $outletName = strtolower(trim($data['outlet'] ?? ''));
            $outletId   = $outletMap[$outletName] ?? null;
            if (! $outletId) {
                $errors[] = "Row $rowNum: outlet '" . ($data['outlet'] ?? '') . "' not found or not accessible";
                $skipped++;
                continue;
            }

            $staffId = $data['staff_id'] ?? null;
            $email   = $data['email'] ?? null;

            // Resolve section by name; auto-create on the fly so a recognised
            // column with unknown values (e.g. "Bar") doesn't silently drop data.
            $sectionId = null;
            $sectionRaw = trim((string) ($data['section'] ?? ''));
            if ($sectionRaw !== '') {
                $sectionKey = strtolower($sectionRaw);
                if (isset($sectionMap[$sectionKey])) {
                    $sectionId = $sectionMap[$sectionKey];
                } else {
                    $createdSection = Section::create([
                        'company_id' => $companyId,
                        'name'       => $sectionRaw,
                        'sort_order' => 99,
                        'is_active'  => true,
                    ]);
                    $sectionId = $createdSection->id;
                    $sectionMap[$sectionKey] = $sectionId;
                }
            }

            // Upsert key preference: staff_id → email → (outlet, name)
            $query = Employee::where('company_id', $companyId);
            $existing = null;
            if ($staffId) {
                $existing = (clone $query)->where('staff_id', $staffId)->first();
            }
            if (! $existing && $email) {
                $existing = (clone $query)->where('email', $email)->first();
            }
            if (! $existing) {
                $existing = (clone $query)
                    ->where('outlet_id', $outletId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->first();
            }

            $payload = [
                'company_id'    => $companyId,
                'outlet_id'     => $outletId,
                'section_id'    => $sectionId,
                'staff_id'      => $staffId ?: null,
                'name'          => $name,
                'designation'   => ($data['designation'] ?? null) ?: null,
                'email'         => $email ?: null,
                'phone'         => ($data['phone'] ?? null) ?: null,
                'is_active'     => true,
            ];

            // New HR fields only overwrite when their column is present in the
            // CSV, so older files don't blank out existing values on update.
            foreach (['join_date' => 'join date', 'typhoid_valid_from' => 'typhoid valid from', 'typhoid_expired_on' => 'typhoid expired on', 'employment_status_date' => 'employment status date'] as $dateKey => $dateLabel) {
                if (! array_key_exists($dateKey, $data)) {
                    continue;
                }
                $parsed = null;
                if ($data[$dateKey] !== '') {
                    try {
                        $parsed = \Carbon\Carbon::parse($data[$dateKey])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Row $rowNum: invalid {$dateLabel} '" . $data[$dateKey] . "' ignored";
                    }
                }
                $payload[$dateKey] = $parsed;
            }
            if (array_key_exists('employment_status', $data)) {
                $statusRaw = strtolower(trim($data['employment_status']));
                $statusMap = [
                    'probation'          => 'probation',
                    'confirmed'          => 'confirmed',
                    'confirm'            => 'confirmed',
                    'extended probation' => 'extended_probation',
                    'extend probation'   => 'extended_probation',
                    'extended_probation' => 'extended_probation',
                    'outsourcing'        => 'outsourcing',
                    'outsource'          => 'outsourcing',
                ];
                if ($statusRaw === '') {
                    $payload['employment_status'] = null;
                } elseif (isset($statusMap[$statusRaw])) {
                    $payload['employment_status'] = $statusMap[$statusRaw];
                } else {
                    $errors[] = "Row $rowNum: unknown employment status '" . $data['employment_status'] . "' ignored";
                }
            }
            if (array_key_exists('outsourcing_company', $data)) {
                $payload['outsourcing_company'] = $data['outsourcing_company'] !== ''
                    ? mb_substr($data['outsourcing_company'], 0, 100)
                    : null;
            }
            if (array_key_exists('food_handler_certified', $data)) {
                $payload['food_handler_certified'] = $parseBool($data['food_handler_certified']);
            }
            if (array_key_exists('food_handler_cert_no', $data)) {
                $payload['food_handler_cert_no'] = $data['food_handler_cert_no'] !== ''
                    ? mb_substr($data['food_handler_cert_no'], 0, 100)
                    : null;
            }
            if (array_key_exists('typhoid_card', $data)) {
                $payload['typhoid_card'] = $parseBool($data['typhoid_card']);
            }

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Employee::create($payload);
                $created++;
            }
        }

        fclose($tmp);

        $this->importResult = [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => array_slice($errors, 0, 20), // cap UI noise
        ];
        $this->csvFile = null;
        $this->resetPage();
    }

    public function downloadTemplate()
    {
        $headers = ['Outlet', 'Employee Name', 'Designation', 'Section', 'Staff ID', 'E-mail', 'Phone Number', 'Join Date', 'Employment Status', 'Employment Status Date', 'Outsourcing Company', 'Food Handler Certified', 'Food Handler Cert No', 'Typhoid Card', 'Typhoid Valid From', 'Typhoid Expired On'];
        $sample  = [
            ['Main Kitchen', 'Ali bin Ahmad',  'Kitchen Helper', 'BOH', 'EMP-001', 'ali@example.com',  '+60123456789', '2024-01-15', 'Confirmed', '2024-07-15', '', 'Yes', 'FHC-2026-0123', 'Yes', '2026-01-10', '2029-01-09'],
            ['Outlet A',     'Siti Nurhaliza', 'Cashier',        'FOH', 'EMP-002', 'siti@example.com', '+60129876543', '2025-06-01', 'Probation', '2026-09-01', '', 'No',  '',              'No',  '', ''],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sample as $row) fputcsv($output, $row);
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response()->streamDownload(
            fn () => print($csv),
            'employees_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        $accessible    = $this->accessibleOutletIds();
        $canViewAll    = $user->canViewAllOutlets();

        // Only show outlets the current user can actually access.
        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', $accessible)
            ->orderBy('name')
            ->get();

        $sections = Section::active()->ordered()->get();

        // Hard-scope the query to accessible outlets regardless of filter —
        // a user cannot list (or act on) employees from outlets outside
        // their outlet-access grants.
        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->orderBy('name');

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('email', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        if ($this->outletFilter !== '') {
            $query->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->sectionFilter !== '') {
            $query->where('section_id', (int) $this->sectionFilter);
        }
        if ($this->statusFilter === 'active')   $query->where('is_active', true);
        if ($this->statusFilter === 'inactive') $query->where('is_active', false);
        if ($this->employmentStatusFilter === 'none') {
            $query->whereNull('employment_status');
        } elseif ($this->employmentStatusFilter !== '') {
            $query->where('employment_status', $this->employmentStatusFilter);
        }

        $employees = $query->paginate(25);

        return view('livewire.hr.employees', compact('employees', 'outlets', 'sections', 'canViewAll'))
            ->layout('layouts.app', ['title' => 'Employees']);
    }
}
