<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Services\Hr\TimeOffBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Only overtime approved AS TIME OFF is a time-off balance.
 *
 * The balance used to count every approved unpaid claim, payroll-destined ones
 * included, on the reasoning that they stayed available until a run stamped
 * paid_at on them. That made `settlement` advisory: overtime explicitly marked
 * to be PAID could be taken as leave instead, so "what is this person owed"
 * had two answers until payday picked one.
 *
 * The other half of the rule is older and stays: a claim nobody has approved
 * is not a balance. Offering pending hours invites somebody to book leave
 * against overtime that is then rejected.
 */
class TimeOffBalanceSettlementTest extends TestCase
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
            'name' => 'Time Off Co', 'slug' => Str::slug('Time Off Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->manager = User::factory()->create(['company_id' => $this->company->id]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH', 'is_active' => true, 'join_date' => '2025-01-01',
            'daily_working_hours' => 8,
        ]);
    }

    private function claim(string $status, string $settlement, float $hours, string $date = '2026-08-10'): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $this->employee->id, 'submitted_by' => $this->manager->id,
            'claim_date' => $date,
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => $hours,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake',
            'status' => $status, 'settlement' => $settlement,
        ]);
    }

    private function balance(): TimeOffBalance
    {
        return app(TimeOffBalance::class);
    }

    public function test_overtime_approved_as_time_off_is_a_balance(): void
    {
        $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $this->assertSame(4.0, $this->balance()->availableHours($this->employee));
    }

    public function test_overtime_approved_for_payroll_is_not(): void
    {
        // It is going to be paid. Offering it as leave as well means the
        // settlement flag decides nothing until payday.
        $this->claim('approved', OvertimeClaim::SETTLE_PAYROLL, 4);

        $this->assertSame(0.0, $this->balance()->availableHours($this->employee));
    }

    public function test_overtime_still_awaiting_approval_is_not(): void
    {
        $this->claim('submitted', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $this->assertSame(0.0, $this->balance()->availableHours($this->employee));
    }

    public function test_a_draft_or_rejected_claim_is_not(): void
    {
        $this->claim('draft', OvertimeClaim::SETTLE_TIME_OFF, 4, '2026-08-10');
        $this->claim('rejected', OvertimeClaim::SETTLE_TIME_OFF, 4, '2026-08-11');

        $this->assertSame(0.0, $this->balance()->availableHours($this->employee));
    }

    public function test_a_claim_already_paid_out_is_not(): void
    {
        $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 4)
            ->forceFill(['paid_at' => now()])->save();

        $this->assertSame(0.0, $this->balance()->availableHours($this->employee));
    }

    public function test_a_mixed_employee_is_owed_only_the_time_off_half(): void
    {
        $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 3, '2026-08-10');
        $this->claim('approved', OvertimeClaim::SETTLE_PAYROLL, 5, '2026-08-11');
        $this->claim('submitted', OvertimeClaim::SETTLE_TIME_OFF, 7, '2026-08-12');

        $this->assertSame(3.0, $this->balance()->availableHours($this->employee));
    }

    public function test_an_allocation_can_only_spend_time_off_hours(): void
    {
        // The guard that turns the balance into a refusal: approving a request
        // for more than the time-off half must fail rather than quietly
        // reaching into payroll-destined overtime.
        $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 2, '2026-08-10');
        $payroll = $this->claim('approved', OvertimeClaim::SETTLE_PAYROLL, 8, '2026-08-11');

        $request = TimeOffRequest::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'off_date' => '2026-09-01',
            'hours' => 6,
            'status' => TimeOffRequest::PENDING,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $this->balance()->allocate($request);
        } finally {
            // The payroll claim must be untouched whether or not it threw.
            $this->assertSame(0.0, (float) $payroll->fresh()->hours_taken_off);
        }
    }

    public function test_hours_taken_against_a_time_off_claim_leave_the_balance(): void
    {
        $claim = $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $request = TimeOffRequest::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'off_date' => '2026-09-01',
            'hours' => 3,
            'status' => TimeOffRequest::PENDING,
        ]);

        $this->balance()->allocate($request);

        $this->assertSame(3.0, (float) $claim->fresh()->hours_taken_off);
        // 4 earned less the 3 now spent.
        $this->assertSame(1.0, $this->balance()->earnedHours($this->employee));
    }

    public function test_cancelling_gives_the_hours_back(): void
    {
        // release() works off the allocation rows rather than the claim query,
        // so this keeps working regardless of what the balance counts.
        $claim = $this->claim('approved', OvertimeClaim::SETTLE_TIME_OFF, 4);

        $request = TimeOffRequest::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'off_date' => '2026-09-01',
            'hours' => 3,
            'status' => TimeOffRequest::PENDING,
        ]);

        $this->balance()->allocate($request);
        $this->balance()->release($request);

        $this->assertSame(0.0, (float) $claim->fresh()->hours_taken_off);
        $this->assertSame(4.0, $this->balance()->earnedHours($this->employee));
    }
}
