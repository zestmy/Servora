<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\ClockEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deleting an employee no longer destroys their history.
 *
 * WRITTEN AFTER SOMEBODY DID IT BY ACCIDENT, from the staff list on a phone.
 * The delete was a hard one and fifteen tables cascade off `employees` in the
 * database, so a single tap took that person's attendance, punches, leave,
 * salary revisions, statutory profile, payroll adjustments, roster entries and
 * scanned identity documents with it — none of it observable by Eloquent, so
 * none of it in the audit log, and none of it recoverable without a backup.
 *
 * THE CASCADE TEST IS THE ONE THAT MATTERS. A confirmation dialog reduces how
 * often the tap happens; only the soft delete changes what the tap costs. If
 * somebody later removes `use SoftDeletes` from Employee, every other
 * assertion in the suite still passes and this file is what fails.
 */
class EmployeeSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Soft Co', 'slug' => Str::slug('Soft Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);
    }

    private function employee(string $name = 'AAA STAFF'): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
        ]);
    }

    public function test_deleting_an_employee_is_a_soft_delete(): void
    {
        $emp = $this->employee();

        $emp->delete();

        $this->assertSoftDeleted('employees', ['id' => $emp->id]);
        $this->assertDatabaseHas('employees', ['id' => $emp->id]);
    }

    /**
     * THE POINT OF THE WHOLE CHANGE. A foreign key cascade fires on a real
     * DELETE and not on a soft one, so the child rows have to still be there.
     */
    public function test_the_child_records_survive_the_delete(): void
    {
        $emp = $this->employee();

        AttendanceRecord::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'work_date' => '2026-07-01', 'status' => 'present',
        ]);
        ClockEvent::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'type' => 'in',
            'work_date' => '2026-07-01', 'happened_at' => '2026-07-01 09:00:00',
        ]);
        EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'type' => 'identity', 'file_path' => 'employee-documents/ic.pdf',
            'original_name' => 'ic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]);

        $emp->delete();

        $this->assertDatabaseHas('attendance_records', ['employee_id' => $emp->id]);
        $this->assertDatabaseHas('clock_events', ['employee_id' => $emp->id]);
        $this->assertDatabaseHas('employee_documents', ['employee_id' => $emp->id]);
    }

    public function test_a_deleted_employee_is_off_the_ordinary_list(): void
    {
        $staying = $this->employee('STAYING');
        $going   = $this->employee('GOING');

        $going->delete();

        $ids = Employee::pluck('id');
        $this->assertTrue($ids->contains($staying->id));
        $this->assertFalse($ids->contains($going->id));

        // And findable again when asked for explicitly, which is what the
        // staff list's Deleted filter does.
        $this->assertTrue(Employee::onlyTrashed()->pluck('id')->contains($going->id));
    }

    public function test_restoring_brings_the_employee_and_their_records_back(): void
    {
        $emp = $this->employee();
        AttendanceRecord::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'work_date' => '2026-07-01', 'status' => 'present',
        ]);

        $emp->delete();
        $emp->restore();

        $this->assertNotSoftDeleted('employees', ['id' => $emp->id]);
        $this->assertTrue(Employee::whereKey($emp->id)->exists());
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $emp->id]);
    }

    /** A force delete still cascades — that is what makes it the force one. */
    public function test_force_deleting_still_removes_everything(): void
    {
        $emp = $this->employee();
        AttendanceRecord::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'work_date' => '2026-07-01', 'status' => 'present',
        ]);

        $emp->forceDelete();

        $this->assertDatabaseMissing('employees', ['id' => $emp->id]);
        $this->assertDatabaseMissing('attendance_records', ['employee_id' => $emp->id]);
    }
}
