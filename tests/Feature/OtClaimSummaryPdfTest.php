<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The period summary counts the same hours the approved OT form prints.
 *
 * The two documents answer one question at different resolutions — one
 * person's page, or everyone's totals — and they sit behind adjacent buttons
 * on the same screen. A summary that counts hours the form beside it leaves
 * out is a reconciliation somebody loses an afternoon to, so time-off
 * overtime is excluded from both.
 *
 * Excluded, but stated: the footer carries the hours alongside the pending
 * and rejected ones, because a grand total that is short without saying why
 * is the number that gets queried.
 */
class OtClaimSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $paid;
    private Employee $onTimeOff;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Summary Co', 'slug' => Str::slug('Summary Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        Permission::findOrCreate('hr.claims', 'web');

        $this->manager   = $this->user();
        $this->paid      = $this->employee('AISYAH');
        $this->onTimeOff = $this->employee('SITI');
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
            'name' => $name, 'employment_status' => 'confirmed', 'is_active' => true,
        ]);
    }

    private function claim(Employee $employee, string $date, string $settlement, float $hours, string $status = 'approved'): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $employee->id, 'submitted_by' => $this->manager->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => $hours,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => $status, 'settlement' => $settlement,
            'approved_by' => $this->manager->id, 'approved_at' => now(),
        ]);
    }

    /**
     * The report's own data. dompdf compresses its streams, so the rendered
     * bytes cannot be searched for a number.
     *
     * @return array<string, mixed>
     */
    private function reportData(): array
    {
        $data = null;

        Event::listen('composing: pdf.ot-claims-summary', function ($view) use (&$data) {
            $data = $view->getData();
        });

        $this->actingAs($this->manager)
            ->get(route('hr.ot-claims.summary-pdf', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk();

        $this->assertNotNull($data, 'The summary was never rendered.');

        return $data;
    }

    /** @param array<string, mixed> $report */
    private function namesOn(array $report): array
    {
        return collect($report['rows'])->map(fn ($r) => $r['employee']?->name)->all();
    }

    public function test_time_off_hours_are_out_of_the_grand_total(): void
    {
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->paid, '2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $this->assertSame(2.0, (float) $this->reportData()['grandTotalHours']);
    }

    public function test_an_employee_whose_ot_is_all_time_off_gets_no_row(): void
    {
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->onTimeOff, '2026-08-10', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $this->assertSame(['AISYAH'], $this->namesOn($this->reportData()));
    }

    public function test_the_excluded_hours_are_stated_in_the_footer(): void
    {
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->onTimeOff, '2026-08-10', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $this->assertSame(4.0, (float) $this->reportData()['timeOffHours']);
    }

    public function test_the_ot_type_totals_drop_the_time_off_hours_too(): void
    {
        // The per-type footer is summed from the same collection, so this
        // fails loudly if the exclusion is ever applied to the rows alone.
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->paid, '2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);

        $this->assertSame(2.0, (float) $this->reportData()['typeTotals']['normal_day']);
    }

    public function test_a_report_with_no_time_off_is_unchanged(): void
    {
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->onTimeOff, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 4);

        $report = $this->reportData();

        $this->assertSame(['AISYAH', 'SITI'], $this->namesOn($report));
        $this->assertSame(6.0, (float) $report['grandTotalHours']);
        $this->assertSame(0.0, (float) $report['timeOffHours']);
    }

    public function test_the_summary_and_the_form_agree_on_the_total(): void
    {
        // The whole point of aligning them. Same period, same scope, same
        // number — whichever button somebody presses.
        $this->claim($this->paid, '2026-08-10', OvertimeClaim::SETTLE_PAYROLL, 2);
        $this->claim($this->paid, '2026-08-11', OvertimeClaim::SETTLE_TIME_OFF, 3);
        $this->claim($this->onTimeOff, '2026-08-12', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $formTotal = 0.0;
        Event::listen('composing: pdf.partials.ot-claim-page', function ($view) use (&$formTotal) {
            $formTotal += (float) $view->getData()['totalHours'];
        });

        $this->actingAs($this->manager)
            ->get(route('hr.ot-claims.pdf', [
                'employee' => 'all', 'from' => '2026-08-01', 'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->assertSame(2.0, $formTotal);
        $this->assertSame($formTotal, (float) $this->reportData()['grandTotalHours']);
    }
}
