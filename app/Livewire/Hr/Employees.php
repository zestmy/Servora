<?php

namespace App\Livewire\Hr;

use App\Models\Section;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

    // Add / edit lives on its own page — see App\Livewire\Hr\EmployeeForm.

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
        return Auth::user()->accessibleOutletIds();
    }

    /**
     * Salary and service point entitlement are pay-sensitive: users without
     * hr.compensation never see them in the list, the form, the CSV template
     * or the exports, and any value they submit is ignored on save.
     */
    protected function canViewPay(): bool
    {
        return Employee::canViewPay(Auth::user());
    }

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingOutletFilter(): void   { $this->resetPage(); }
    public function updatingSectionFilter(): void  { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }
    public function updatingEmploymentStatusFilter(): void { $this->resetPage(); }

    /**
     * Persist a drag-and-drop row order. Order lives in employees.sort_order,
     * shared with the Attendance Record grid so both screens stay in sync.
     */
    public function reorderRows(array $orderedIds): void
    {
        $allowed = Employee::whereIn('id', $orderedIds)
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->pluck('id')
            ->flip();

        // Offset by the current page so a drag on page 2 stays after page 1.
        $index = (($this->paginators['page'] ?? 1) - 1) * 25;
        foreach ($orderedIds as $id) {
            if (! isset($allowed[(int) $id])) continue;
            Employee::where('id', (int) $id)->update(['sort_order' => $index++]);
        }
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
            'halal awareness training' => 'halal_training',
            'halal training'           => 'halal_training',
            'halal'                    => 'halal_training',
            'halal training date'      => 'halal_training_date',
            'halal date attended'      => 'halal_training_date',
            'date attended'            => 'halal_training_date',
            'typhoid valid from'  => 'typhoid_valid_from',
            'typhoid valid'       => 'typhoid_valid_from',
            'typhoid expired on'  => 'typhoid_expired_on',
            'typhoid expiry'      => 'typhoid_expired_on',
            'typhoid expiry date' => 'typhoid_expired_on',
            'typhoid expired'     => 'typhoid_expired_on',
            // Break allowance is NOT pay-gated: the employee form shows the
            // field to everyone and the modal advertises the column to
            // everyone, so gating it here only made the value vanish silently
            // for users without hr.compensation. It is a clock-in setting.
            'break minutes'       => 'break_minutes',
            'break duration'      => 'break_minutes',
            'break'               => 'break_minutes',
        ];

        // Pay columns are only recognised for users with hr.compensation —
        // for everyone else the headers stay unmapped and the values are
        // dropped, so an import can't be used to write salary sideways.
        $canViewPay = $this->canViewPay();
        if ($canViewPay) {
            $aliasMap += [
                'service points entitlement' => 'service_points_entitlement',
                'service points'             => 'service_points_entitlement',
                'service pts'                => 'service_points_entitlement',
                'basic salary'               => 'basic_salary',
                'salary'                     => 'basic_salary',
                'monthly salary'             => 'basic_salary',
                'basic pay'                  => 'basic_salary',
                'pay type'                   => 'pay_type',
                'pay basis'                  => 'pay_type',
                'salary type'                => 'pay_type',
            ];
        }

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
            foreach (['join_date' => 'join date', 'typhoid_valid_from' => 'typhoid valid from', 'typhoid_expired_on' => 'typhoid expired on', 'employment_status_date' => 'employment status date', 'halal_training_date' => 'halal training date'] as $dateKey => $dateLabel) {
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
                    'partimer'           => 'partimer',
                    'part timer'         => 'partimer',
                    'part-timer'         => 'partimer',
                    'part time'          => 'partimer',
                    'part-time'          => 'partimer',
                    'parttime'           => 'partimer',
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
            if (array_key_exists('halal_training', $data)) {
                $payload['halal_training'] = $parseBool($data['halal_training']);
            }
            if (array_key_exists('break_minutes', $data)) {
                $brRaw = str_replace([',', ' '], '', $data['break_minutes']);
                // Blank means "follow the duty roster's rest duration"; 0 means
                // "no break allowance at all". They are different answers, so an
                // empty cell must write NULL rather than fall through to 0.
                if ($brRaw === '') {
                    $payload['break_minutes'] = null;
                    // is_numeric plus a whole-number check rather than
                    // ctype_digit, so a spreadsheet writing "60.0" still
                    // imports while "60.5" is rejected as not a minute count.
                } elseif (is_numeric($brRaw) && (float) $brRaw == (int) (float) $brRaw
                    && (int) (float) $brRaw >= 0 && (int) (float) $brRaw <= 1440) {
                    $payload['break_minutes'] = (int) (float) $brRaw;
                } else {
                    // Left out of the payload entirely, so an existing value
                    // survives a bad cell instead of being blanked.
                    $errors[] = "Row $rowNum: invalid break minutes '" . $data['break_minutes'] . "' ignored";
                }
            }
            if (array_key_exists('service_points_entitlement', $data)) {
                $spRaw = str_replace(',', '', $data['service_points_entitlement']);
                if ($spRaw === '') {
                    $payload['service_points_entitlement'] = null;
                } elseif (is_numeric($spRaw)) {
                    $payload['service_points_entitlement'] = round((float) $spRaw, 2);
                } else {
                    $errors[] = "Row $rowNum: invalid service points '" . $data['service_points_entitlement'] . "' ignored";
                }
            }
            if (array_key_exists('basic_salary', $data)) {
                $salRaw = str_replace([',', ' '], '', $data['basic_salary']);
                if ($salRaw === '') {
                    $payload['basic_salary'] = null;
                    $payload['pay_type']     = null;
                } elseif (is_numeric($salRaw)) {
                    $payload['basic_salary'] = round((float) $salRaw, 2);
                    // Default the basis when the CSV carries an amount but no
                    // Pay Type column — monthly is the common case.
                    $payload['pay_type'] = $payload['pay_type'] ?? 'monthly';
                } else {
                    $errors[] = "Row $rowNum: invalid salary '" . $data['basic_salary'] . "' ignored";
                }
            }
            if (array_key_exists('pay_type', $data) && ($payload['basic_salary'] ?? null) !== null) {
                $ptRaw = strtolower(trim($data['pay_type']));
                $ptMap = ['monthly' => 'monthly', 'month' => 'monthly', 'daily' => 'daily', 'day' => 'daily', 'hourly' => 'hourly', 'hour' => 'hourly'];
                if ($ptRaw === '') {
                    $payload['pay_type'] = 'monthly';
                } elseif (isset($ptMap[$ptRaw])) {
                    $payload['pay_type'] = $ptMap[$ptRaw];
                } else {
                    $errors[] = "Row $rowNum: unknown pay type '" . $data['pay_type'] . "' ignored";
                }
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
        // Break Minutes carries a sample on one row and a blank on the other,
        // because blank and 0 mean different things and the template is where
        // that gets noticed.
        $headers = ['Outlet', 'Employee Name', 'Designation', 'Section', 'Staff ID', 'E-mail', 'Phone Number', 'Join Date', 'Employment Status', 'Employment Status Date', 'Outsourcing Company', 'Food Handler Certified', 'Food Handler Cert No', 'Typhoid Card', 'Typhoid Valid From', 'Typhoid Expired On', 'Halal Awareness Training', 'Halal Training Date', 'Break Minutes'];
        $sample  = [
            ['Main Kitchen', 'Ali bin Ahmad',  'Kitchen Helper', 'BOH', 'EMP-001', 'ali@example.com',  '+60123456789', '2024-01-15', 'Confirmed', '2024-07-15', '', 'Yes', 'FHC-2026-0123', 'Yes', '2026-01-10', '2029-01-09', 'Yes', '2026-03-12', '60'],
            ['Outlet A',     'Siti Nurhaliza', 'Cashier',        'FOH', 'EMP-002', 'siti@example.com', '+60129876543', '2025-06-01', 'Probation', '2026-09-01', '', 'No',  '',              'No',  '', '', 'No', '', ''],
        ];

        // Pay columns only appear in the template for users who may see them.
        if ($this->canViewPay()) {
            $headers   = array_merge($headers, ['Service Points Entitlement', 'Basic Salary', 'Pay Type']);
            $sample[0] = array_merge($sample[0], ['1.50', '2500.00', 'Monthly']);
            $sample[1] = array_merge($sample[1], ['', '85.00', 'Daily']);
        }

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
            ->inListOrder();

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
        } elseif ($this->employmentStatusFilter === 'exclude_outsourcing') {
            $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            });
        } elseif ($this->employmentStatusFilter !== '') {
            $query->where('employment_status', $this->employmentStatusFilter);
        }

        $canViewPay = $this->canViewPay();
        if (! $canViewPay) {
            // Never select what the view isn't allowed to render.
            $query->select(array_values(array_diff(
                Schema::getColumnListing('employees'),
                Employee::SENSITIVE_PAY_ATTRIBUTES
            )));
        }

        $employees = $query->paginate(25);

        return view('livewire.hr.employees', compact('employees', 'outlets', 'sections', 'canViewAll', 'canViewPay'))
            ->layout('layouts.app', ['title' => 'Employees']);
    }
}
