<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The approved OT form is what payroll pays against, so it carries payable
 * hours only.
 *
 * Overtime settled as time off is taken back as leave and never reaches a
 * payslip, so it is left out of the document entirely — and somebody whose
 * approved OT is ALL time off gets no page at all.
 *
 * What it must not do is go quietly short. The excluded hours are stated in
 * the same footer that already accounts for pending and rejected claims,
 * because a total that is short without saying why is the one somebody
 * queries against their payslip.
 *
 * The settlement plumbing is still pinned here even though this document now
 * excludes time off: the partial is shared, and $hoursBySettlement reaching it
 * by scope inheritance rather than by name is what let the all-employees page
 * print time-off hours as payable in the first place.
 */
class OtClaimSettlementPdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Settle Co', 'slug' => Str::slug('Settle Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $section = Section::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true,
        ]);

        Permission::findOrCreate('hr.claims', 'web');

        $this->manager = $this->user();

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH', 'section_id' => $section->id,
            'employment_status' => 'confirmed', 'is_active' => true,
        ]);
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

    private function approvedClaim(string $date, string $settlement, float $hours): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->employee->id, 'submitted_by' => $this->manager->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => $hours,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => $settlement,
            'approved_by' => $this->manager->id, 'approved_at' => now(),
        ]);
    }

    /**
     * The data the printed page actually received.
     *
     * Read off the partial rather than the file: dompdf compresses its
     * streams, so the rendered bytes cannot be searched for a number.
     *
     * @return array<string, mixed>
     */
    private function pageData(string $employeeParam): array
    {
        $data = null;

        Event::listen('composing: pdf.partials.ot-claim-page', function ($view) use (&$data) {
            $data = $view->getData();
        });

        $this->actingAs($this->manager)
            ->get(route('hr.ot-claims.pdf', [
                'employee' => $employeeParam, 'from' => '2026-08-01', 'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertNotNull($data, 'The claim page was never rendered.');

        return $data;
    }

    public function test_time_off_hours_are_left_off_the_all_employees_form(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $page = $this->pageData('all');

        $this->assertSame([2.0], $this->hoursOn($page));
        $this->assertSame(2.0, (float) $page['totalHours']);
    }

    public function test_time_off_hours_are_left_off_the_single_employee_form(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $page = $this->pageData((string) $this->employee->id);

        $this->assertSame([2.0], $this->hoursOn($page));
        $this->assertSame(2.0, (float) $page['totalHours']);
    }

    public function test_the_excluded_hours_are_stated_rather_than_silently_dropped(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        // A total that is short without saying why is the one somebody queries
        // against their payslip.
        $this->assertSame(3.0, (float) $this->pageData('all')['timeOffHours']);
    }

    public function test_an_employee_whose_ot_is_all_time_off_gets_no_page(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $rendered = false;
        Event::listen('composing: pdf.partials.ot-claim-page', function () use (&$rendered) {
            $rendered = true;
        });

        $this->actingAs($this->manager)
            ->get(route('hr.ot-claims.pdf', [
                'employee' => 'all', 'from' => '2026-08-01', 'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertFalse($rendered, 'Nothing payable means no page at all.');
    }

    public function test_an_ordinary_payroll_form_is_unchanged(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_PAYROLL, 3);

        $page = $this->pageData('all');

        $this->assertSame([2.0, 3.0], $this->hoursOn($page));
        $this->assertSame(0.0, (float) $page['timeOffHours']);
    }

    public function test_a_claim_that_never_named_a_settlement_still_prints(): void
    {
        /*
         * `settlement` is NOT NULL defaulting to 'payroll', which is what
         * every claim written before the column existed became. Excluding
         * time off rather than selecting payroll means those historical rows
         * keep printing without anything being back-filled — a form already
         * signed and filed must not shrink when it is reprinted.
         */
        $claim = OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->employee->id, 'submitted_by' => $this->manager->id,
            'claim_date' => '2026-08-10',
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => 2,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake', 'status' => 'approved',
            // settlement deliberately absent
        ]);

        $this->assertSame(OvertimeClaim::SETTLE_PAYROLL, $claim->fresh()->settlement);
        $this->assertSame([2.0], $this->hoursOn($this->pageData('all')));
    }

    public function test_the_partial_is_handed_its_settlement_data_by_name(): void
    {
        // The bug this file was opened for: $hoursBySettlement reached the
        // single-employee partial through the parent view's scope and never
        // reached the all-employees one at all.
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);

        foreach (['all', (string) $this->employee->id] as $target) {
            $this->assertArrayHasKey('hoursBySettlement', $this->pageData($target));
        }
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<int, float>
     */
    private function hoursOn(array $page): array
    {
        return collect($page['claims'])->map(fn ($c) => (float) $c->total_ot_hours)->values()->all();
    }
}
