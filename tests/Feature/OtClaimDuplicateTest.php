<?php

namespace Tests\Feature;

use App\Livewire\Hr\OvertimeClaims;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * One live OT claim per employee per date.
 *
 * The cost of the mistake is the reason for the rule: two claims for one shift
 * are two lots of hours, and once both are approved they are two lots of pay —
 * nothing downstream reconciles that, the payslip simply adds them up.
 *
 * A rejection is the deliberate exception. It means "fix this and send it
 * again", so a rejected claim must not stand in the way of the corrected one,
 * or the gate leaves somebody unpaid for hours they actually worked.
 */
class OtClaimDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private Employee $employee;
    private Employee $colleague;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Dup Co', 'slug' => Str::slug('Dup Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->kitchen = Section::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true,
        ]);

        Permission::findOrCreate('hr.claims', 'web');

        $this->manager = $this->user();

        $this->employee  = $this->employee('AISYAH');
        $this->colleague = $this->employee('BALQIS');
    }

    private function user(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo('hr.claims');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function employee(string $name): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'section_id' => $this->kitchen->id,
            'employment_status' => 'confirmed', 'is_active' => true,
        ]);
    }

    private function claim(Employee $employee, string $date, string $status = 'submitted'): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $employee->id, 'submitted_by' => $this->manager->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => 2,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake', 'status' => $status,
        ]);
    }

    /**
     * The claims screen, filled in and saved.
     *
     * Driven directly rather than through Livewire::test(): rendering this
     * screen runs its weekly-trend aggregate, which is raw MySQL (WEEKDAY /
     * DATE_SUB) and cannot execute on the SQLite the suite uses. save() runs
     * the whole validate-guard-write path without touching the view.
     */
    private function fillClaim(Employee $employee, string $date, ?int $editingId = null): OvertimeClaim|OvertimeClaims
    {
        $this->actingAs($this->manager);

        $component = new OvertimeClaims();
        $component->employee_id    = $employee->id;
        $component->claim_date     = $date;
        $component->ot_time_start  = '18:00';
        $component->ot_time_end    = '20:00';
        $component->total_ot_hours = '2';
        $component->ot_type        = 'normal_day';
        $component->reason         = 'Stocktake';
        $component->editingId      = $editingId;

        return $component;
    }

    // ── The gate ──

    public function test_a_second_claim_on_the_same_date_is_refused(): void
    {
        $this->claim($this->employee, '2026-08-10');

        $component = $this->fillClaim($this->employee, '2026-08-10');
        $component->save('submit');

        $this->assertTrue($component->getErrorBag()->has('claim_date'));
        $this->assertStringContainsString(
            'already has a submitted OT claim on 10 Aug 2026',
            $component->getErrorBag()->first('claim_date')
        );
        $this->assertSame(1, OvertimeClaim::where('employee_id', $this->employee->id)->count());
    }

    public function test_the_message_names_the_claim_it_collided_with(): void
    {
        $this->claim($this->employee, '2026-08-10', 'approved');

        $component = $this->fillClaim($this->employee, '2026-08-10');
        $component->save('submit');

        // Hunting for the offending record is the whole cost of a bare
        // "duplicate" message, so the times and hours are in the text.
        $message = $component->getErrorBag()->first('claim_date');
        $this->assertStringContainsString('approved', $message);
        $this->assertStringContainsString('18:00–20:00', $message);
        $this->assertStringContainsString('2.0h', $message);
    }

    public function test_a_different_date_for_the_same_employee_is_allowed(): void
    {
        $this->claim($this->employee, '2026-08-10');

        $component = $this->fillClaim($this->employee, '2026-08-11');
        $component->save('submit');

        $this->assertFalse($component->getErrorBag()->has('claim_date'));
        $this->assertSame(2, OvertimeClaim::where('employee_id', $this->employee->id)->count());
    }

    public function test_the_same_date_for_a_different_employee_is_allowed(): void
    {
        $this->claim($this->employee, '2026-08-10');

        $component = $this->fillClaim($this->colleague, '2026-08-10');
        $component->save('submit');

        $this->assertFalse($component->getErrorBag()->has('claim_date'));
        $this->assertSame(1, OvertimeClaim::where('employee_id', $this->colleague->id)->count());
    }

    public function test_a_rejected_claim_does_not_block_the_corrected_resubmission(): void
    {
        // The deliberate exception: a rejection is an instruction to fix the
        // claim and send it again.
        $this->claim($this->employee, '2026-08-10', 'rejected');

        $component = $this->fillClaim($this->employee, '2026-08-10');
        $component->save('submit');

        $this->assertFalse($component->getErrorBag()->has('claim_date'));
        $this->assertSame(1, OvertimeClaim::where('employee_id', $this->employee->id)
            ->where('status', 'submitted')->count());
    }

    public function test_a_deleted_claim_does_not_block(): void
    {
        $this->claim($this->employee, '2026-08-10')->delete();

        $component = $this->fillClaim($this->employee, '2026-08-10');
        $component->save('submit');

        $this->assertFalse($component->getErrorBag()->has('claim_date'));
    }

    public function test_a_claim_is_not_its_own_duplicate_when_edited(): void
    {
        $existing = $this->claim($this->employee, '2026-08-10', 'draft');

        $component = $this->fillClaim($this->employee, '2026-08-10', $existing->id);
        $component->reason = 'Corrected reason';
        $component->save();

        $this->assertFalse($component->getErrorBag()->has('claim_date'));
        $this->assertSame('Corrected reason', $existing->fresh()->reason);
    }

    public function test_editing_a_claim_onto_a_date_another_claim_holds_is_refused(): void
    {
        $this->claim($this->employee, '2026-08-10');
        $moving = $this->claim($this->employee, '2026-08-12', 'draft');

        $component = $this->fillClaim($this->employee, '2026-08-10', $moving->id);
        $component->save();

        $this->assertTrue($component->getErrorBag()->has('claim_date'));
        $this->assertSame('2026-08-12', $moving->fresh()->claim_date->toDateString());
    }

    // ── The notice ──

    public function test_records_that_predate_the_gate_are_reported_not_repaired(): void
    {
        // Written straight to the table, as the screen would have allowed
        // before the gate existed.
        $this->claim($this->employee, '2026-08-10');
        $this->claim($this->employee, '2026-08-10', 'draft');
        $this->claim($this->colleague, '2026-08-10');

        $groups = OvertimeClaim::duplicateGroups(OvertimeClaim::query());

        $this->assertCount(1, $groups);
        $this->assertSame($this->employee->id, (int) $groups->first()->employee_id);
        $this->assertSame(2, (int) $groups->first()->claim_count);

        // Reported, not repaired — both rows survive for a human to judge.
        $this->assertSame(2, OvertimeClaim::where('employee_id', $this->employee->id)->count());
    }

    public function test_a_rejected_claim_is_not_counted_as_a_duplicate(): void
    {
        $this->claim($this->employee, '2026-08-10');
        $this->claim($this->employee, '2026-08-10', 'rejected');

        $this->assertCount(0, OvertimeClaim::duplicateGroups(OvertimeClaim::query()));
    }

    public function test_a_draft_and_an_approved_claim_are_the_pair_worth_naming(): void
    {
        $this->claim($this->employee, '2026-08-10', 'draft');
        $this->claim($this->employee, '2026-08-10', 'approved');

        $groups = OvertimeClaim::duplicateGroups(OvertimeClaim::query());

        $this->assertCount(1, $groups);
        $this->assertSame(2, (int) $groups->first()->claim_count);
    }

    public function test_the_bar_ignores_the_status_filter_that_would_hide_the_pair(): void
    {
        $this->claim($this->employee, '2026-08-10', 'draft');
        $this->claim($this->employee, '2026-08-10', 'approved');

        $this->actingAs($this->manager);

        $component = new OvertimeClaims();
        $component->statusFilter = 'approved';   // hides one half of the pair
        $component->dateFrom     = '2026-08-01';
        $component->dateTo       = '2026-08-31';

        $scope = OvertimeClaim::query();
        $this->callProtected($component, 'duplicateFilter')->apply($scope, [$this->outlet->id]);

        $this->assertCount(1, OvertimeClaim::duplicateGroups($scope));
    }

    public function test_the_bar_still_follows_the_date_range(): void
    {
        $this->claim($this->employee, '2026-07-10');
        $this->claim($this->employee, '2026-07-10', 'draft');

        $this->actingAs($this->manager);

        $component = new OvertimeClaims();
        $component->dateFrom = '2026-08-01';
        $component->dateTo   = '2026-08-31';

        $scope = OvertimeClaim::query();
        $this->callProtected($component, 'duplicateFilter')->apply($scope, [$this->outlet->id]);

        $this->assertCount(0, OvertimeClaim::duplicateGroups($scope));
    }

    // ── The roster path ──

    public function test_the_roster_will_not_raise_a_claim_over_a_hand_typed_one(): void
    {
        // Matching on roster_entry_id alone only stops a roster duplicating
        // itself; it says nothing about the claim a supervisor already typed
        // in for the same shift.
        $this->claim($this->employee, '2026-08-10');

        $this->assertNotNull(
            OvertimeClaim::duplicateFor($this->employee->id, '2026-08-10 00:00:00'),
            'A full datetime must resolve to the same date as the stored claim.'
        );
    }

    private function callProtected(object $object, string $method): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invoke($object);
    }
}
