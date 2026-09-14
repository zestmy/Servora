<?php

namespace Tests\Feature;

use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Hourly Rate of Pay (HRP) that overtime is priced at divides by the
 * employee's own working day when one is set on their record. A 7.5-hour
 * contract priced off the company's 8 hours underpays every OT hour.
 */
class PayrollHourlyRateUsesEmployeeWorkingDayTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'HRP Co', 'slug' => Str::slug('HRP Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    private function employeeWithOvertime(?float $dailyHours): Employee
    {
        $employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'NUR AISYAH ' . uniqid(), 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'daily_working_hours' => $dailyHours,
        ]);

        OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $employee->id, 'submitted_by' => $this->user->id,
            'claim_date' => '2026-07-10',
            'ot_time_start' => '18:00', 'ot_time_end' => '22:00', 'total_ot_hours' => 4,
            'hours_taken_off' => 0, 'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => 'approved', 'settlement' => OvertimeClaim::SETTLE_PAYROLL,
        ]);

        return $employee;
    }

    private function lineFor(Employee $employee)
    {
        $run = app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse('2026-07-01'),
            $this->outlet->id, $this->user->id,
        );

        return $run->lines()->where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_overtime_uses_the_employee_s_own_working_day(): void
    {
        $employee = $this->employeeWithOvertime(7.5);

        // HRP = 3000 ÷ 26 ÷ 7.5 = 15.3846; 4 h × 1.5 = RM92.31 (not RM86.54 at 8 h).
        $this->assertEquals(92.31, (float) $this->lineFor($employee)->ot_amount);
    }

    public function test_a_blank_working_day_follows_the_company_default(): void
    {
        $employee = $this->employeeWithOvertime(null);

        // 3000 ÷ 26 ÷ 8 = 14.4231; 4 h × 1.5 = RM86.54.
        $this->assertEquals(86.54, (float) $this->lineFor($employee)->ot_amount);
    }

    public function test_the_company_default_itself_can_be_seven_and_a_half(): void
    {
        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->daily_working_hours = 7.5;
        $c->save();

        $employee = $this->employeeWithOvertime(null);

        $this->assertEquals(92.31, (float) $this->lineFor($employee)->ot_amount);
    }

    public function test_time_off_uses_the_same_working_day(): void
    {
        $balance = app(\App\Services\Hr\TimeOffBalance::class);

        $ownDay = $this->employeeWithOvertime(7.5);
        $this->assertEquals(7.5, $balance->workingDayHours($ownDay));
        $this->assertEquals(2.0, $balance->asDays($ownDay, 15));

        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->daily_working_hours = 7.5;
        $c->save();

        $companyDefault = $this->employeeWithOvertime(null);
        $this->assertEquals(7.5, $balance->workingDayHours($companyDefault));
        $this->assertEquals(1.0, $balance->asDays($companyDefault, 7.5));
    }

    public function test_hourly_rate_prefers_the_employee_hours_over_the_setting(): void
    {
        $settings = new CompensationSetting(['daily_working_hours' => 8, 'monthly_working_days' => 26]);

        $this->assertEqualsWithDelta(15.3846, $settings->hourlyRate(3000, 'monthly', 7.5), 0.0001);
        $this->assertEqualsWithDelta(14.4231, $settings->hourlyRate(3000, 'monthly'), 0.0001);
        $this->assertEqualsWithDelta(13.3333, $settings->hourlyRate(100, 'daily', 7.5), 0.0001);
        $this->assertEqualsWithDelta(75.0, $settings->dailyRate(10, 'hourly', null, 7.5), 0.0001);
    }
}
