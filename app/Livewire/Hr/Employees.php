<?php

namespace App\Livewire\Hr;

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
    public string $search       = '';
    public string $outletFilter = '';
    public string $statusFilter = 'active';

    // Add/edit modal
    public bool  $showForm        = false;
    public ?int  $editingId       = null;
    public ?int  $f_outlet_id     = null;
    public string $f_staff_id     = '';
    public string $f_name         = '';
    public string $f_designation  = '';
    public string $f_department   = '';
    public string $f_email        = '';
    public string $f_phone        = '';
    public bool   $f_is_active    = true;

    // CSV import modal
    public bool  $showImport   = false;
    public $csvFile            = null;
    public ?array $importResult = null;

    protected function rules(): array
    {
        return [
            'f_outlet_id'    => 'required|integer|exists:outlets,id',
            'f_staff_id'     => 'nullable|string|max:100',
            'f_name'         => 'required|string|max:255',
            'f_designation'  => 'nullable|string|max:255',
            'f_department'   => 'nullable|string|max:255',
            'f_email'        => 'nullable|email|max:255',
            'f_phone'        => 'nullable|string|max:50',
            'f_is_active'    => 'boolean',
        ];
    }

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingOutletFilter(): void  { $this->resetPage(); }
    public function updatingStatusFilter(): void  { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $emp = Employee::findOrFail($id);
        $this->editingId    = $emp->id;
        $this->f_outlet_id  = $emp->outlet_id;
        $this->f_staff_id   = $emp->staff_id ?? '';
        $this->f_name       = $emp->name;
        $this->f_designation = $emp->designation ?? '';
        $this->f_department = $emp->department ?? '';
        $this->f_email      = $emp->email ?? '';
        $this->f_phone      = $emp->phone ?? '';
        $this->f_is_active  = (bool) $emp->is_active;
        $this->showForm     = true;
    }

    public function save(): void
    {
        $this->validate();
        $user = Auth::user();

        $data = [
            'company_id'  => $user->company_id,
            'outlet_id'   => $this->f_outlet_id,
            'staff_id'    => $this->f_staff_id ?: null,
            'name'        => $this->f_name,
            'designation' => $this->f_designation ?: null,
            'department'  => $this->f_department ?: null,
            'email'       => $this->f_email ?: null,
            'phone'       => $this->f_phone ?: null,
            'is_active'   => $this->f_is_active,
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
        $this->editingId     = null;
        $this->f_outlet_id   = null;
        $this->f_staff_id    = '';
        $this->f_name        = '';
        $this->f_designation = '';
        $this->f_department  = '';
        $this->f_email       = '';
        $this->f_phone       = '';
        $this->f_is_active   = true;
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

        // Build outlet lookup for this company (name lowercased → id)
        $outletMap = Outlet::where('company_id', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->all();

        $path = $this->csvFile->getRealPath();
        $fh   = fopen($path, 'r');
        if (! $fh) {
            session()->flash('error', 'Could not read CSV file.');
            return;
        }

        $headers = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 0;

        // Normalise header tokens so small wording differences still map.
        $aliasMap = [
            'outlet'          => 'outlet',
            'employee name'   => 'name',
            'name'            => 'name',
            'designation'     => 'designation',
            'position'        => 'designation',
            'department'      => 'department',
            'staff id'        => 'staff_id',
            'staff no'        => 'staff_id',
            'employee id'     => 'staff_id',
            'e-mail'          => 'email',
            'email'           => 'email',
            'phone number'    => 'phone',
            'phone'           => 'phone',
            'mobile'          => 'phone',
            'contact'         => 'phone',
        ];

        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $headers = array_map(function ($h) use ($aliasMap) {
                    $key = strtolower(trim((string) $h));
                    return $aliasMap[$key] ?? null;
                }, $row);
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
                'company_id'  => $companyId,
                'outlet_id'   => $outletId,
                'staff_id'    => $staffId ?: null,
                'name'        => $name,
                'designation' => ($data['designation'] ?? null) ?: null,
                'department'  => ($data['department'] ?? null) ?: null,
                'email'       => $email ?: null,
                'phone'       => ($data['phone'] ?? null) ?: null,
                'is_active'   => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Employee::create($payload);
                $created++;
            }
        }

        fclose($fh);

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

        $query = Employee::with('outlet')->orderBy('name');

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('email', 'like', $s)
                  ->orWhere('designation', 'like', $s)
                  ->orWhere('department', 'like', $s);
            });
        }
        if ($this->outletFilter !== '') {
            $query->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->statusFilter === 'active')   $query->where('is_active', true);
        if ($this->statusFilter === 'inactive') $query->where('is_active', false);

        $employees = $query->paginate(25);

        return view('livewire.hr.employees', compact('employees', 'outlets'))
            ->layout('layouts.app', ['title' => 'Employees']);
    }
}
