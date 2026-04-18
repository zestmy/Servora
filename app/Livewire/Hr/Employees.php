<?php

namespace App\Livewire\Hr;

use App\Models\Department;
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
    public string $departmentFilter = '';
    public string $statusFilter     = 'active';

    // Add/edit modal
    public bool  $showForm          = false;
    public ?int  $editingId         = null;
    public ?int  $f_outlet_id       = null;
    public ?int  $f_department_id   = null;
    public string $f_staff_id       = '';
    public string $f_name           = '';
    public string $f_designation    = '';
    public string $f_email          = '';
    public string $f_phone          = '';
    public bool   $f_is_active      = true;

    // CSV import modal
    public bool  $showImport   = false;
    public $csvFile            = null;
    public ?array $importResult = null;

    protected function rules(): array
    {
        return [
            'f_outlet_id'      => 'required|integer|exists:outlets,id',
            'f_department_id'  => 'nullable|integer|exists:departments,id',
            'f_staff_id'       => 'nullable|string|max:100',
            'f_name'           => 'required|string|max:255',
            'f_designation'    => 'nullable|string|max:255',
            'f_email'          => 'nullable|email|max:255',
            'f_phone'          => 'nullable|string|max:50',
            'f_is_active'      => 'boolean',
        ];
    }

    public function updatingSearch(): void            { $this->resetPage(); }
    public function updatingOutletFilter(): void      { $this->resetPage(); }
    public function updatingDepartmentFilter(): void  { $this->resetPage(); }
    public function updatingStatusFilter(): void      { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $emp = Employee::findOrFail($id);
        $this->editingId       = $emp->id;
        $this->f_outlet_id     = $emp->outlet_id;
        $this->f_department_id = $emp->department_id;
        $this->f_staff_id      = $emp->staff_id ?? '';
        $this->f_name          = $emp->name;
        $this->f_designation   = $emp->designation ?? '';
        $this->f_email         = $emp->email ?? '';
        $this->f_phone         = $emp->phone ?? '';
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
            'department_id' => $this->f_department_id ?: null,
            'staff_id'      => $this->f_staff_id ?: null,
            'name'          => $this->f_name,
            'designation'   => $this->f_designation ?: null,
            'email'         => $this->f_email ?: null,
            'phone'         => $this->f_phone ?: null,
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
        $emp->update(['is_active' => ! $emp->is_active]);
    }

    public function delete(int $id): void
    {
        Employee::findOrFail($id)->delete();
        session()->flash('success', 'Employee deleted.');
    }

    protected function resetForm(): void
    {
        $this->editingId       = null;
        $this->f_outlet_id     = null;
        $this->f_department_id = null;
        $this->f_staff_id      = '';
        $this->f_name          = '';
        $this->f_designation   = '';
        $this->f_email         = '';
        $this->f_phone         = '';
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

        $user      = Auth::user();
        $companyId = $user->company_id;

        // Build outlet + department lookups for this company (name lowercased → id).
        $outletMap = Outlet::where('company_id', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        $departmentMap = Department::where('company_id', $companyId)
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
            'department'      => 'department',
            'dept'            => 'department',
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
        ];

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
                            'Expected: Outlet, Employee Name, Designation, Department, Staff ID, E-mail, Phone Number',
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
                $errors[] = "Row $rowNum: outlet '" . ($data['outlet'] ?? '') . "' not found for your company";
                $skipped++;
                continue;
            }

            $staffId = $data['staff_id'] ?? null;
            $email   = $data['email'] ?? null;

            // Resolve department by name; auto-create on the fly so a recognised
            // column with unknown values (e.g. "Bar") doesn't silently drop data.
            $deptId = null;
            $deptRaw = trim((string) ($data['department'] ?? ''));
            if ($deptRaw !== '') {
                $deptKey = strtolower($deptRaw);
                if (isset($departmentMap[$deptKey])) {
                    $deptId = $departmentMap[$deptKey];
                } else {
                    $created_dept = Department::create([
                        'company_id' => $companyId,
                        'name'       => $deptRaw,
                        'sort_order' => 99,
                        'is_active'  => true,
                    ]);
                    $deptId = $created_dept->id;
                    $departmentMap[$deptKey] = $deptId;
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
                'department_id' => $deptId,
                'staff_id'      => $staffId ?: null,
                'name'          => $name,
                'designation'   => ($data['designation'] ?? null) ?: null,
                'email'         => $email ?: null,
                'phone'         => ($data['phone'] ?? null) ?: null,
                'is_active'     => true,
            ];

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
        $headers = ['Outlet', 'Employee Name', 'Designation', 'Department', 'Staff ID', 'E-mail', 'Phone Number'];
        $sample  = [
            ['Main Kitchen', 'Ali bin Ahmad',  'Kitchen Helper', 'Kitchen', 'EMP-001', 'ali@example.com',  '+60123456789'],
            ['Outlet A',     'Siti Nurhaliza', 'Cashier',        'Front',   'EMP-002', 'siti@example.com', '+60129876543'],
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

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $departments = Department::active()->ordered()->get();

        $query = Employee::with(['outlet', 'department'])->orderBy('name');

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
        if ($this->departmentFilter !== '') {
            $query->where('department_id', (int) $this->departmentFilter);
        }
        if ($this->statusFilter === 'active')   $query->where('is_active', true);
        if ($this->statusFilter === 'inactive') $query->where('is_active', false);

        $employees = $query->paginate(25);

        return view('livewire.hr.employees', compact('employees', 'outlets', 'departments'))
            ->layout('layouts.app', ['title' => 'Employees']);
    }
}
