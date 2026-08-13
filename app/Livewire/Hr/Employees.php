<?php

namespace App\Livewire\Hr;

use App\Models\Section;
use App\Models\Employee;
use App\Models\ComplianceSetting;
use App\Models\Outlet;
use App\Services\Hr\DocumentExpiry;
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

    /**
     * @param  ?string  $outlet  which outlet to open on, carried back from the
     *         employee form so that saving returns you where you were rather
     *         than to your own outlet. 'all' is a real answer here — somebody
     *         who deliberately widened the list should not be narrowed again
     *         on their way back — which is why it is a string and not an id.
     * @param  ?string  $section     the section filter, same round trip.
     * @param  ?string  $employment  the employment filter, same round trip.
     * @param  ?string  $status      active / inactive / '' (all).
     *
     * ALL FOUR DROPDOWNS COME BACK, not just the outlet. Someone working
     * through the outsourced staff, or through the resigned ones, was being
     * returned to a list showing everybody after each save — the record they
     * had just edited was still there, so it read as the filter having been
     * ignored rather than dropped. The search box is deliberately NOT carried:
     * it is something you type to find one person, not a place in the list.
     */
    public function mount(
        ?string $outlet = null,
        ?string $section = null,
        ?string $employment = null,
        ?string $status = null,
    ): void {
        /*
         * READ OFF THE QUERY STRING, because the arguments above never arrive.
         *
         * A full-page Livewire component resolves its mount arguments from
         * ROUTE parameters, and /hr/employees has none — outlet, section,
         * employment and status are all query string. So every one of these
         * was null on a real request and the whole round trip was dead:
         * saving, Cancel and the "<" all landed on a list that had reverted to
         * the user's own outlet.
         *
         * It passed its tests because they call Livewire::test(Employees,
         * ['outlet' => …]), which hands mount() the arguments directly — the
         * one path a browser never takes. EmployeeListFilterRoundTripTest now
         * drives it over HTTP instead.
         */
        $outlet     ??= request()->query('outlet');
        $section    ??= request()->query('section');
        $employment ??= request()->query('employment');
        $status     ??= request()->query('status');

        if ($outlet !== null) {
            $this->outletFilter = $outlet === 'all' ? '' : $outlet;
        }

        // 'all' is spelled out for the same reason as the outlet: an empty
        // string in a URL is indistinguishable from the parameter being absent,
        // and those two mean opposite things here.
        if ($section !== null) {
            $this->sectionFilter = $section === 'all' ? '' : $section;
        }

        if ($employment !== null) {
            $this->employmentStatusFilter = $employment === 'all' ? '' : $employment;
        }

        if ($status !== null) {
            $this->statusFilter = $status === 'all' ? '' : $status;
        }

        // Default the outlet filter to the user's active outlet so screens feel
        // consistent with the rest of Servora (they only see their current
        // outlet unless they explicitly opt into "All"). Skipped entirely when
        // an outlet was named above, including when it was named as "all".
        if ($outlet === null && $this->outletFilter === '') {
            $activeOutletId = Auth::user()?->activeOutletId();
            if ($activeOutletId) $this->outletFilter = (string) $activeOutletId;
        }

        $this->showCompliance  = (bool) session('hr.employees.compliance_open', true);
        $this->showFoodHandler = (bool) session('hr.employees.food_handler_open', true);
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

    /** Compliance card open/closed, remembered per user across visits. */
    public bool $showCompliance = true;

    public function toggleCompliance(): void
    {
        $this->showCompliance = ! $this->showCompliance;
        session(['hr.employees.compliance_open' => $this->showCompliance]);
    }

    /** Most uncertified names listed on the card before it says "and N more". */
    public const FOOD_HANDLER_NAME_LIMIT = 50;

    /** Food handler name list open/closed, remembered per user across visits. */
    public bool $showFoodHandler = true;

    public function toggleFoodHandler(): void
    {
        $this->showFoodHandler = ! $this->showFoodHandler;
        session(['hr.employees.food_handler_open' => $this->showFoodHandler]);
    }

    public function updatingSearch(): void         { $this->resetPage(); }
    public function updatingOutletFilter(): void   { $this->resetPage(); }
    public function updatingSectionFilter(): void  { $this->resetPage(); }
    public function updatingStatusFilter(): void   { $this->resetPage(); }
    public function updatingEmploymentStatusFilter(): void { $this->resetPage(); }

    /**
     * Asking for the resigned staff widens the Active/Inactive filter.
     *
     * A resignation whose date has passed sets the employee INACTIVE — that is
     * what `hr:apply-resignations` does every night — so "Resigned" and the
     * default "Active" are very nearly contradictory: the only people in both
     * are those who have handed in notice but not yet left. Somebody choosing
     * Resigned to see who has left was shown an almost empty list and had to
     * discover for themselves that a second dropdown also had to be moved.
     *
     * It widens to All rather than switching to Inactive, because both halves
     * are wanted: the people who have gone and the ones still working out
     * their notice.
     *
     * Only from the DEFAULT. Somebody who has deliberately picked Active or
     * Inactive is answering a narrower question, and moving their control out
     * from under them would be the more annoying bug. The change is made to
     * the dropdown itself rather than quietly inside the query, so the list
     * always matches what the controls say.
     */
    public function updatedEmploymentStatusFilter(string $value): void
    {
        if ($value === 'resigned' && $this->statusFilter === 'active') {
            $this->statusFilter = '';
        }
    }

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
        $active = ! $emp->is_active;

        // Switching a resigned employee back on means they returned, so the
        // resignation is cleared with it. Leaving it set would have
        // hr:apply-resignations quietly switch them off again overnight.
        $emp->update($active && $emp->resignationIsEffective()
            ? ['is_active' => true, 'employment_status' => null, 'employment_status_date' => null]
            : ['is_active' => $active]);
    }

    public function delete(int $id): void
    {
        // Outlet access alone used to be the whole check here, so anyone who could open
        // the staff list could delete records outright. Deleting an employee is its own
        // ability now; the outlet check still applies on top of it.
        abort_unless(Auth::user()?->canDo('hr.employees.delete'), 403);

        $emp = Employee::findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            session()->flash('error', 'You do not have access to this employee.');
            return;
        }

        // Written BEFORE the delete: an update() afterwards would have to go
        // looking for the row it had just removed. Same shape as deleteEvent()
        // on the punch review screen, which solved this first.
        $emp->forceFill(['deleted_by' => Auth::id()])->saveQuietly();
        $emp->delete();

        // Says where it went. A message that only says "deleted" is what sends
        // somebody to look for a backup.
        session()->flash('success', $emp->name . ' removed from the staff list. '
            . 'Their records are kept — switch the Status filter to "Deleted" to put them back.');
    }

    /**
     * Put a deleted employee back.
     *
     * `deleted_by` is cleared rather than kept, following the punch screen: a
     * live record carrying "deleted by Aisha" reads as a record that is still
     * deleted. Who removed them and who put them back both survive in the
     * audit log, which the observer writes for deleted and restored alike.
     */
    public function restore(int $id): void
    {
        abort_unless(Auth::user()?->canDo('hr.employees.delete'), 403);

        $emp = Employee::withTrashed()->findOrFail($id);
        if (! in_array((int) $emp->outlet_id, $this->accessibleOutletIds(), true)) {
            session()->flash('error', 'You do not have access to this employee.');
            return;
        }

        $emp->restore();
        $emp->forceFill(['deleted_by' => null])->saveQuietly();

        session()->flash('success', $emp->name . ' restored, with their attendance, leave and pay records.');
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
            'food handler expiry'      => 'food_handler_expired_on',
            'food handler expired on'  => 'food_handler_expired_on',
            'food handler expiry date' => 'food_handler_expired_on',
            'halal training expiry'      => 'halal_training_expired_on',
            'halal training expired on'  => 'halal_training_expired_on',
            'halal expiry'               => 'halal_training_expired_on',
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
            foreach (['join_date' => 'join date', 'typhoid_valid_from' => 'typhoid valid from', 'typhoid_expired_on' => 'typhoid expired on', 'employment_status_date' => 'employment status date', 'halal_training_date' => 'halal training date', 'food_handler_expired_on' => 'food handler expiry', 'halal_training_expired_on' => 'halal training expiry'] as $dateKey => $dateLabel) {
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
                    'internship'         => 'internship',
                    'intern'             => 'internship',
                    'internship (fixed term)' => 'internship',
                    'practical'          => 'internship',
                    'practical training' => 'internship',
                    'outsourcing'        => 'outsourcing',
                    'outsource'          => 'outsourcing',
                    'resigned'           => 'resigned',
                    'resign'             => 'resigned',
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

            // Same rule as the employee form: a leaving date already behind us
            // deactivates the row, so importing a resignation list doesn't
            // leave everyone on it still showing up on next month's attendance
            // grid. Columns absent from the CSV fall back to what is on file.
            $resStatus = array_key_exists('employment_status', $payload)
                ? $payload['employment_status']
                : $existing?->employment_status;
            $resDate = array_key_exists('employment_status_date', $payload)
                ? $payload['employment_status_date']
                : $existing?->employment_status_date;

            if (Employee::resignationTookEffect($resStatus, $resDate)) {
                $payload['is_active'] = false;
            } elseif ($existing) {
                // Never re-activate an existing row on import — the CSV has no
                // Active column, so the unconditional true above is only ever
                // meant as a default for newly created staff.
                unset($payload['is_active']);
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
        $headers = ['Outlet', 'Employee Name', 'Designation', 'Section', 'Staff ID', 'E-mail', 'Phone Number', 'Join Date', 'Employment Status', 'Employment Status Date', 'Outsourcing Company', 'Food Handler Certified', 'Food Handler Cert No', 'Typhoid Card', 'Typhoid Valid From', 'Typhoid Expired On', 'Food Handler Expiry', 'Halal Awareness Training', 'Halal Training Date', 'Halal Training Expiry', 'Break Minutes'];
        $sample  = [
            ['Main Kitchen', 'Ali bin Ahmad',  'Kitchen Helper', 'BOH', 'EMP-001', 'ali@example.com',  '+60123456789', '2024-01-15', 'Confirmed', '2024-07-15', '', 'Yes', 'FHC-2026-0123', '2028-06-30', 'Yes', '2026-01-10', '2029-01-09', 'Yes', '2026-03-12', '2029-03-11', '60'],
            ['Outlet A',     'Siti Nurhaliza', 'Cashier',        'FOH', 'EMP-002', 'siti@example.com', '+60129876543', '2025-06-01', 'Probation', '2026-09-01', '', 'No',  '',              '',           'No',  '', '', 'No', '', '', ''],
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

        /*
         * "Deleted" is a value ON the status filter rather than a filter of
         * its own, which is where this diverges from the punch review screen.
         * There, deleted and status are genuinely independent — a deleted
         * punch is still flagged or still clean, and folding the two together
         * would make "deleted AND flagged" unaskable. Here the second question
         * does not exist: nobody needs the deleted-and-inactive staff as
         * distinct from the deleted-and-active ones, and one dropdown with the
         * answer in it is what somebody hunting for a person they just removed
         * will actually find. The flash message on delete points straight at
         * this option by name.
         *
         * Everything else in the list is unaffected: Laravel's SoftDeletingScope
         * keeps deleted staff out of Active, Inactive and All without a single
         * call site here or anywhere else having to remember to exclude them.
         */
        if ($this->statusFilter === 'deleted') $query->onlyTrashed();
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

        // Compliance follows the outlet and section you are looking at, but
        // never the search box or the status filters: it answers "what is due
        // in this part of the business", not "what is due among the rows
        // currently matching my search". Active staff only — DocumentExpiry
        // narrows to those, since nobody chases a leaver for a renewal.
        $complianceQuery = Employee::query()->whereIn('outlet_id', $accessible ?: [0]);
        if ($this->outletFilter !== '')  $complianceQuery->where('outlet_id', (int) $this->outletFilter);
        if ($this->sectionFilter !== '') $complianceQuery->where('section_id', (int) $this->sectionFilter);

        $compliance = $this->showCompliance
            ? app(DocumentExpiry::class)->summarise($complianceQuery, $companyId)
            : null;

        $complianceSettings = ComplianceSetting::forCompany($companyId);

        // Food handler is a held/not-held question, not an expiry one — it is
        // one-off for most kitchens, so it is not in the expiry card at all.
        // Counted over the same scope: active staff in the outlet and section
        // being viewed, ignoring the search box and the status filters.
        $foodHandler = (clone $complianceQuery)
            ->where('is_active', true)
            ->selectRaw('SUM(food_handler_certified = 1) as taken, SUM(food_handler_certified = 0 OR food_handler_certified IS NULL) as not_taken')
            ->first();

        $foodHandlerStats = [
            'taken'     => (int) ($foodHandler->taken ?? 0),
            'not_taken' => (int) ($foodHandler->not_taken ?? 0),
        ];
        $foodHandlerStats['total'] = $foodHandlerStats['taken'] + $foodHandlerStats['not_taken'];

        // Who is missing it, in the same order every staff list uses. A count
        // tells you there is a problem; the names are what someone acts on, so
        // they load with the card rather than behind a second screen. Capped
        // at 50 with the shortfall stated — a kitchen where nobody is
        // certified would otherwise render the entire roster above the table.
        $foodHandlerStats['missing'] = ($this->showFoodHandler && $foodHandlerStats['not_taken'] > 0)
            ? (clone $complianceQuery)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('food_handler_certified', false)
                                     ->orWhereNull('food_handler_certified'))
                ->with(['outlet:id,name', 'section:id,name'])
                ->inListOrder()
                ->limit(self::FOOD_HANDLER_NAME_LIMIT)
                ->get(['id', 'name', 'staff_id', 'outlet_id', 'section_id'])
            : collect();

        return view('livewire.hr.employees', compact(
            'employees', 'outlets', 'sections', 'canViewAll', 'canViewPay',
            'compliance', 'complianceSettings', 'foodHandlerStats'
        ))->layout('layouts.app', ['title' => 'Employees']);
    }
}
