<?php

namespace Tests\Feature;

use App\Livewire\Hr\OvertimeClaims;
use App\Livewire\Settings\PayComponents;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\OvertimeRateType;
use App\Models\Outlet;
use App\Models\User;
use App\Scopes\CompanyScope;
use App\Services\Hr\CompensationSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A custom OT type with its own fixed rate — "Part Time", RM15/hour — beside
 * Normal Day / Rest Day / Public Holiday, which are the employee's hourly rate
 * × a multiplier.
 */
class CustomOvertimeRateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'OT Co', 'slug' => Str::slug('OT Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->manager->companies()->syncWithoutDetaching([$this->company->id]);
        $this->manager->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.claims', 'hr.compensation'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->manager->givePermissionTo(['hr.claims', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(?float $salary = 2600): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Staff ' . uniqid(), 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => $salary, 'pay_type' => 'monthly',
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);
    }

    private function rateType(string $name = 'Part Time', float $rate = 15, bool $active = true): OvertimeRateType
    {
        return OvertimeRateType::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $this->company->id, 'name' => $name,
            'hourly_rate' => $rate, 'is_active' => $active,
        ]);
    }

    /** The claim form, filled in and saved — see OtClaimDuplicateTest::fillClaim(). */
    private function saveClaim(Employee $e, string $type, string $date = '2026-07-10', string $hours = '4'): OvertimeClaims
    {
        $this->actingAs($this->manager);

        $component = new OvertimeClaims();
        $component->employee_id    = $e->id;
        $component->claim_date     = $date;
        $component->ot_time_start  = '18:00';
        $component->ot_time_end    = '22:00';
        $component->total_ot_hours = $hours;
        $component->ot_type        = $type;
        $component->reason         = 'Event';
        $component->save('submit');

        return $component;
    }

    private function approve(): void
    {
        OvertimeClaim::withoutGlobalScope(CompanyScope::class)->update([
            'status' => 'approved', 'approved_at' => now(), 'approved_by' => $this->manager->id,
        ]);
    }

    private function payrollRow(Employee $e): array
    {
        return app(CompensationSummary::class)->forMonth(
            Employee::query()->whereKey($e->id), $this->company->id, Carbon::parse('2026-07-01'),
        )['rows']->first();
    }

    // ── Settings ──────────────────────────────────────────────────────────

    public function test_a_custom_ot_type_is_added_on_pay_components(): void
    {
        Livewire::actingAs($this->manager)->test(PayComponents::class)
            ->set('rateTypeName', 'Part Time')
            ->set('rateTypeRate', '15')
            ->call('saveRateType')
            ->assertHasNoErrors()
            ->assertSee('Part Time')
            ->assertSee('RM15.00/h');

        $type = OvertimeRateType::withoutGlobalScope(CompanyScope::class)->sole();
        $this->assertSame('Part Time', $type->name);
        $this->assertEqualsWithDelta(15.0, (float) $type->hourly_rate, 0.001);
    }

    public function test_names_are_unique_and_a_rate_is_required(): void
    {
        $this->rateType();

        Livewire::actingAs($this->manager)->test(PayComponents::class)
            ->set('rateTypeName', 'Part Time')
            ->set('rateTypeRate', '')
            ->call('saveRateType')
            ->assertHasErrors(['rateTypeName', 'rateTypeRate']);
    }

    public function test_a_type_used_on_claims_cannot_be_deleted(): void
    {
        $type = $this->rateType();
        $this->saveClaim($this->employee(), $type->key());

        Livewire::actingAs($this->manager)->test(PayComponents::class)
            ->call('deleteRateType', $type->id);

        $this->assertNotNull($type->fresh(), 'A type claims point at must be deactivated, not deleted.');
    }

    // ── The claim ─────────────────────────────────────────────────────────

    public function test_the_claim_form_offers_active_custom_types_with_their_rate(): void
    {
        $partTime = $this->rateType();
        $retired  = $this->rateType('Old Rate', 10, active: false);

        $this->actingAs($this->manager);
        $options = (new OvertimeClaims())->otTypeOptions();

        $this->assertSame('Normal Day', $options['normal_day']);
        $this->assertSame('Part Time (RM15.00/h)', $options[$partTime->key()]);
        $this->assertArrayNotHasKey($retired->key(), $options);
    }

    public function test_the_rate_is_copied_onto_the_claim(): void
    {
        $type = $this->rateType();
        $this->saveClaim($this->employee(), $type->key());

        $claim = OvertimeClaim::withoutGlobalScope(CompanyScope::class)->sole();
        $this->assertSame($type->key(), $claim->ot_type);
        $this->assertEqualsWithDelta(15.0, (float) $claim->ot_hourly_rate, 0.001);
        $this->assertSame('Part Time', $claim->otTypeLabel());
        $this->assertTrue($claim->hasFixedRate());
    }

    public function test_a_statutory_claim_carries_no_fixed_rate(): void
    {
        $this->saveClaim($this->employee(), 'rest_day');

        $claim = OvertimeClaim::withoutGlobalScope(CompanyScope::class)->sole();
        $this->assertNull($claim->ot_hourly_rate);
        $this->assertSame('Rest Day', $claim->otTypeLabel());
    }

    public function test_an_inactive_type_cannot_be_chosen(): void
    {
        $retired = $this->rateType('Old Rate', 10, active: false);

        $this->expectException(ValidationException::class);
        $this->saveClaim($this->employee(), $retired->key());
    }

    // ── Payroll ───────────────────────────────────────────────────────────

    public function test_payroll_pays_hours_times_the_custom_rate(): void
    {
        $e = $this->employee();
        $type = $this->rateType();
        $this->saveClaim($e, $type->key(), '2026-07-10', '4');
        $this->saveClaim($e, $type->key(), '2026-07-11', '2.5');
        $this->approve();

        $row = $this->payrollRow($e);

        // 6.5 h × RM15 — not the employee's hourly rate × a multiplier.
        $this->assertEqualsWithDelta(97.50, $row['ot_amount'], 0.001);
        $this->assertEqualsWithDelta(6.5, $row['ot_by_type'][$type->key()]['hours'], 0.001);
        $this->assertSame('Part Time', $row['ot_by_type'][$type->key()]['label']);
    }

    public function test_it_is_paid_without_a_salary_on_record(): void
    {
        $e = $this->employee(salary: null);
        $type = $this->rateType();
        $this->saveClaim($e, $type->key(), '2026-07-10', '4');
        $this->approve();

        $row = $this->payrollRow($e);

        $this->assertEqualsWithDelta(60.00, $row['ot_amount'], 0.001);
        $this->assertFalse($row['ot_unrated'], 'A fixed-rate claim is priced; it is not "unrated".');
    }

    public function test_changing_the_rate_does_not_reprice_claims_already_made(): void
    {
        $e = $this->employee();
        $type = $this->rateType('Part Time', 15);
        $this->saveClaim($e, $type->key(), '2026-07-10', '2');

        $type->update(['hourly_rate' => 20]);
        $this->saveClaim($e, $type->key(), '2026-07-11', '2');
        $this->approve();

        // 2 h × RM15 + 2 h × RM20.
        $this->assertEqualsWithDelta(70.00, $this->payrollRow($e)['ot_amount'], 0.001);
    }

    public function test_statutory_ot_is_priced_as_before_alongside_it(): void
    {
        // RM2,600 / 26 days / 8 h = RM12.50/h; normal day × 1.5 = RM18.75/h.
        $e = $this->employee(2600);
        $type = $this->rateType();
        $this->saveClaim($e, 'normal_day', '2026-07-10', '2');
        $this->saveClaim($e, $type->key(), '2026-07-11', '2');
        $this->approve();

        $row = $this->payrollRow($e);

        $this->assertEqualsWithDelta(37.50, $row['ot_by_type']['normal_day']['amount'], 0.001);
        $this->assertEqualsWithDelta(30.00, $row['ot_by_type'][$type->key()]['amount'], 0.001);
        $this->assertEqualsWithDelta(67.50, $row['ot_amount'], 0.001);
    }
}
