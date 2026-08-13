<?php

namespace Tests\Feature;

use App\Models\ClockEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeFaceDescriptor;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deleting an employee takes their files with them.
 *
 * FOUND WHILE AUDITING `hr.employees.delete`. Four tables hang files off an
 * employee — scanned paperwork, their photograph, face enrolment images and
 * clock-in selfies — and every one of those foreign keys is
 * `cascadeOnDelete()`. That cascade happens in MySQL, and MySQL does not raise
 * Eloquent events, so the `deleted` hook on EmployeeDocument that removes its
 * file never ran on this path. The rows vanished and the files stayed: IC
 * scans and passports on disk with nothing in the database pointing at them,
 * so nothing in the product could ever find them again.
 *
 * Which is exactly the failure EmployeeDocument's own hook exists to prevent —
 * "the file is the record" — reached by the one route that bypassed it. It was
 * also a quiet way around the document-delete gate: somebody who may not
 * remove a scan could remove the employee, which dropped the rows and left the
 * files behind. The worse of both outcomes.
 *
 * Production had zero orphans when this was found, so it is fixed before it
 * cost anything.
 *
 * NOW HUNG ON forceDelete(), NOT delete(). Employee started soft-deleting
 * after somebody removed a member of staff by accident from a phone, and an
 * ordinary delete leaves every row in place so it can be undone from the staff
 * list. Shredding the passport scan on the way out would hand back a hollow
 * employee on restore — a record with no documents, no photograph and no
 * enrolment, and nothing to say anything had ever been there. The files go
 * when the record genuinely will not come back, which is the same answer
 * ClockEvent reached, and the two tests at the bottom of this file pin it in
 * both directions.
 */
class EmployeeDeleteRemovesFilesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Purge Co', 'slug' => Str::slug('Purge Co') . '-' . uniqid(),
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

    private function writeFile(string $path): string
    {
        Storage::disk('local')->put($path, 'bytes');

        return $path;
    }

    public function test_a_scanned_document_is_removed_from_disk(): void
    {
        $emp = $this->employee();
        $path = $this->writeFile('employee-documents/ic.pdf');

        EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'type' => 'identity', 'file_path' => $path,
            'original_name' => 'ic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]);

        $emp->forceDelete();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('employee_documents', ['file_path' => $path]);
    }

    public function test_the_employees_photograph_is_removed(): void
    {
        $emp = $this->employee();
        $path = $this->writeFile('employee-photos/face.jpg');
        $emp->update(['photo_path' => $path]);

        $emp->forceDelete();

        Storage::disk('local')->assertMissing($path);
    }

    /** Biometric enrolment images — the most sensitive of the four. */
    public function test_face_enrolment_photos_are_removed(): void
    {
        $emp = $this->employee();
        $path = $this->writeFile('face/enrol.jpg');

        EmployeeFaceDescriptor::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'photo_path' => $path, 'descriptor' => json_encode(array_fill(0, 4, 0.1)),
        ]);

        $emp->forceDelete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_clock_in_selfies_are_removed(): void
    {
        $emp = $this->employee();

        $paths = [];
        foreach (range(1, 3) as $i) {
            $paths[] = $path = $this->writeFile("clock/selfie-$i.jpg");

            ClockEvent::create([
                'company_id' => $this->company->id, 'employee_id' => $emp->id,
                'outlet_id' => $this->outlet->id, 'type' => 'in',
                'work_date' => '2026-07-0' . $i, 'happened_at' => '2026-07-0' . $i . ' 09:00:00',
                'selfie_path' => $path,
            ]);
        }

        $emp->forceDelete();

        foreach ($paths as $p) {
            Storage::disk('local')->assertMissing($p);
        }
    }

    /** All four at once, which is what a real deletion looks like. */
    public function test_every_kind_of_file_goes_together(): void
    {
        $emp = $this->employee();

        $doc    = $this->writeFile('employee-documents/passport.pdf');
        $photo  = $this->writeFile('employee-photos/staff.jpg');
        $face   = $this->writeFile('face/descriptor.jpg');
        $selfie = $this->writeFile('clock/punch.jpg');

        $emp->update(['photo_path' => $photo]);
        EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'type' => 'identity', 'file_path' => $doc,
            'original_name' => 'p.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]);
        EmployeeFaceDescriptor::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'photo_path' => $face, 'descriptor' => json_encode([0.1]),
        ]);
        ClockEvent::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'type' => 'in',
            'work_date' => '2026-07-01', 'happened_at' => '2026-07-01 09:00:00',
            'selfie_path' => $selfie,
        ]);

        $this->assertCount(4, $emp->ownedFilePaths());

        $emp->forceDelete();

        foreach ([$doc, $photo, $face, $selfie] as $p) {
            Storage::disk('local')->assertMissing($p);
        }
    }

    /** Nobody else's files may be caught in it. */
    public function test_another_employees_files_are_left_alone(): void
    {
        $going  = $this->employee('GOING');
        $saying = $this->employee('STAYING');

        $theirs = $this->writeFile('employee-documents/theirs.pdf');
        $mine   = $this->writeFile('employee-documents/mine.pdf');

        foreach ([[$going, $theirs], [$saying, $mine]] as [$e, $path]) {
            EmployeeDocument::create([
                'company_id' => $this->company->id, 'employee_id' => $e->id,
                'type' => 'identity', 'file_path' => $path,
                'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
            ]);
        }

        $going->forceDelete();

        Storage::disk('local')->assertMissing($theirs);
        Storage::disk('local')->assertExists($mine);
    }

    /** An employee with nothing attached deletes without complaint. */
    public function test_an_employee_with_no_files_deletes_cleanly(): void
    {
        $emp = $this->employee();

        $this->assertSame([], $emp->ownedFilePaths());

        $emp->forceDelete();

        $this->assertDatabaseMissing('employees', ['id' => $emp->id]);
    }

    // ── Deleting the child records on their own ───────────────────────────

    /** A discarded face capture takes its photograph with it, wherever from. */
    public function test_deleting_a_face_capture_removes_its_photo(): void
    {
        $emp  = $this->employee();
        $path = $this->writeFile('face/bad-capture.jpg');

        $descriptor = EmployeeFaceDescriptor::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'photo_path' => $path, 'descriptor' => json_encode([0.1]),
        ]);

        $descriptor->delete();

        Storage::disk('local')->assertMissing($path);
    }

    /**
     * A SOFT-deleted punch KEEPS its selfie, and this is the correct
     * behaviour rather than an oversight.
     *
     * ClockEvent soft-deletes and the screen can restore one. The selfie is
     * the evidence of that punch: destroying it on delete would make the
     * restore hollow, hand back a record whose photograph had gone, and throw
     * away the one thing an audit of a disputed punch would want to see.
     */
    public function test_a_soft_deleted_punch_keeps_its_selfie(): void
    {
        $emp  = $this->employee();
        $path = $this->writeFile('clock/disputed.jpg');

        $event = ClockEvent::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'type' => 'in',
            'work_date' => '2026-07-01', 'happened_at' => '2026-07-01 09:00:00',
            'selfie_path' => $path,
        ]);

        $event->delete();

        $this->assertSoftDeleted('clock_events', ['id' => $event->id]);
        Storage::disk('local')->assertExists($path);

        // And the restore is whole, not a record with a hole in it.
        $event->restore();
        Storage::disk('local')->assertExists($path);
    }

    /** Once it genuinely will not come back, the selfie goes. */
    public function test_force_deleting_a_punch_removes_its_selfie(): void
    {
        $emp  = $this->employee();
        $path = $this->writeFile('clock/gone-for-good.jpg');

        $event = ClockEvent::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'outlet_id' => $this->outlet->id, 'type' => 'in',
            'work_date' => '2026-07-01', 'happened_at' => '2026-07-01 09:00:00',
            'selfie_path' => $path,
        ]);

        $event->forceDelete();

        Storage::disk('local')->assertMissing($path);
    }

    /** A path already gone from disk must not derail the delete. */
    public function test_a_missing_file_does_not_block_the_delete(): void
    {
        $emp = $this->employee();

        EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'type' => 'identity', 'file_path' => 'employee-documents/never-written.pdf',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]);

        $emp->forceDelete();

        $this->assertDatabaseMissing('employees', ['id' => $emp->id]);
    }

    // ── Soft delete keeps everything ──────────────────────────────────────

    /**
     * An ordinary delete from the staff list KEEPS the files, and this is the
     * correct behaviour rather than the bug this file was opened for.
     *
     * The employee can be restored from that screen. Destroying their IC scan
     * and their photograph on the way out would make the restore a lie — the
     * record comes back, the paperwork does not, and nothing on screen says
     * why. Identical reasoning to the soft-deleted punch keeping its selfie,
     * two tests up.
     */
    public function test_a_soft_deleted_employee_keeps_their_files(): void
    {
        $emp   = $this->employee();
        $doc   = $this->writeFile('employee-documents/ic.pdf');
        $photo = $this->writeFile('employee-photos/face.jpg');

        $emp->update(['photo_path' => $photo]);
        EmployeeDocument::create([
            'company_id' => $this->company->id, 'employee_id' => $emp->id,
            'type' => 'identity', 'file_path' => $doc,
            'original_name' => 'ic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
        ]);

        $emp->delete();

        $this->assertSoftDeleted('employees', ['id' => $emp->id]);
        Storage::disk('local')->assertExists($doc);
        Storage::disk('local')->assertExists($photo);

        // And the restore is whole, not a record with a hole in it.
        $emp->restore();
        Storage::disk('local')->assertExists($doc);
        Storage::disk('local')->assertExists($photo);
        $this->assertDatabaseHas('employee_documents', ['file_path' => $doc]);
    }
}
