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
 * Approved OT settled as time off must SAY so on the printed form.
 *
 * Both halves are approved overtime and both belong on the record — the person
 * worked those hours either way — but only the payroll half will ever reach a
 * payslip, and this is the document somebody checks their pay against. A total
 * that silently mixes them is the one number that gets disputed.
 *
 * The all-employees print did exactly that: the controller computed the split
 * per employee, the view never handed it to the partial, and the partial's
 * fallback quietly printed time-off hours as though they were payable. The
 * single-employee print escaped only because @include inherits its parent's
 * variables and that view happened to have one to inherit. Both pass it by
 * name now, and this pins both.
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
     * The settlement split as the printed page actually received it.
     *
     * Read off the partial's own data: dompdf compresses its streams, so the
     * rendered bytes cannot be searched for the words on the page.
     *
     * @return array<string, float>
     */
    private function splitOnPage(string $employeeParam): array
    {
        $split = null;

        Event::listen('composing: pdf.partials.ot-claim-page', function ($view) use (&$split) {
            $split = collect($view->getData()['hoursBySettlement'] ?? [])
                ->map(fn ($v) => (float) $v)
                ->all();
        });

        $this->actingAs($this->manager)
            ->get(route('hr.ot-claims.pdf', [
                'employee' => $employeeParam, 'from' => '2026-08-01', 'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertNotNull($split, 'The claim page was never rendered.');

        return $split;
    }

    public function test_the_all_employees_print_carries_the_settlement_split(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $split = $this->splitOnPage('all');

        // Without this the page prints 5.00 hrs undifferentiated, and 3 of them
        // are never going to appear on a payslip.
        $this->assertSame(2.0, $split['payroll'] ?? null);
        $this->assertSame(3.0, $split['time_off'] ?? null);
    }

    public function test_the_single_employee_print_carries_the_settlement_split(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->approvedClaim('2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $split = $this->splitOnPage((string) $this->employee->id);

        $this->assertSame(2.0, $split['payroll'] ?? null);
        $this->assertSame(3.0, $split['time_off'] ?? null);
    }

    public function test_an_all_time_off_employee_is_not_reported_as_payable(): void
    {
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $split = $this->splitOnPage('all');

        $this->assertSame(4.0, $split['time_off'] ?? null);
        $this->assertArrayNotHasKey('payroll', $split);
    }

    public function test_an_all_payroll_page_stays_as_it_was(): void
    {
        // The split box only renders when there IS a split, so an ordinary
        // claim sheet must not sprout a "Settled As" column.
        $this->approvedClaim('2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);

        $split = $this->splitOnPage('all');

        $this->assertSame(0.0, $split['time_off'] ?? 0.0);
        $this->assertSame(2.0, $split['payroll'] ?? null);
    }
}
