<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use App\Services\Payroll\RunPeriods;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A run can carry a period per input, and by default carries none.
 *
 * Phase 2 of the payroll period work: the columns and the resolver exist, and
 * NOTHING READS THEM YET. That is deliberate — the schema lands first so the
 * phase that threads them through the calculation is a change to arithmetic
 * alone, with the storage already proven.
 *
 * The property this whole phase turns on is inheritance: six nulls on every
 * run written so far and on every ordinary run written from now on, resolving
 * to period_start/period_end. If that ever stops being true, every historical
 * run silently starts describing a period nobody chose for it.
 */
class PayrollRunComponentPeriodsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Periods Co', 'slug' => Str::slug('Periods Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'SITI NURHALIZA', 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    private function build(): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], Carbon::parse('2026-08-01'),
            $this->outlet->id, $this->user->id,
        );
    }

    // ── The default ───────────────────────────────────────────────────────

    public function test_a_run_generated_today_carries_no_component_periods(): void
    {
        $run = $this->build();

        foreach (RunPeriods::COMPONENTS as $component) {
            $this->assertNull($run->{$component . '_from'}, $component);
            $this->assertNull($run->{$component . '_to'}, $component);
        }

        $this->assertFalse($run->hasComponentPeriods());
    }

    public function test_an_unset_input_resolves_to_the_runs_own_period(): void
    {
        $run = $this->build();

        foreach (RunPeriods::COMPONENTS as $component) {
            [$from, $to] = $run->periodFor($component);

            $this->assertSame($run->period_start->toDateString(), $from->toDateString(), $component);
            $this->assertSame($run->period_end->toDateString(), $to->toDateString(), $component);
        }
    }

    // ── Storage ───────────────────────────────────────────────────────────

    public function test_a_stored_period_reads_back_on_the_run(): void
    {
        $run = $this->build();

        $periods = new RunPeriods(
            Carbon::parse($run->period_start),
            Carbon::parse($run->period_end),
            [
                RunPeriods::OVERTIME       => [Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')],
                RunPeriods::SERVICE_CHARGE => [Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')],
            ],
        );

        $run->forceFill($periods->columns())->save();

        $read = $run->fresh();

        $this->assertSame('2026-07-01', $read->periodFor(RunPeriods::OVERTIME)[0]->toDateString());
        $this->assertSame('2026-07-31', $read->periodFor(RunPeriods::OVERTIME)[1]->toDateString());
        $this->assertSame('2026-08-31', $read->periodFor(RunPeriods::SERVICE_CHARGE)[1]->toDateString());

        // Untouched, and still the run's own.
        $this->assertSame($read->period_start->toDateString(),
            $read->periodFor(RunPeriods::ATTENDANCE)[0]->toDateString());

        $this->assertTrue($read->hasComponentPeriods());
        $this->assertSame([RunPeriods::OVERTIME, RunPeriods::SERVICE_CHARGE],
            $read->periods()->customComponents());
    }

    /**
     * One date on its own cannot describe a range, and pairing it with the
     * master's other end would invent a period nobody chose. Reachable only by
     * writing the column directly, which is exactly when a quiet half-answer
     * would do the most damage.
     */
    public function test_a_half_written_range_is_ignored_rather_than_half_applied(): void
    {
        $run = $this->build();
        $run->forceFill(['overtime_from' => '2026-07-01', 'overtime_to' => null])->save();

        $read = $run->fresh();

        $this->assertFalse($read->periods()->isCustom(RunPeriods::OVERTIME));
        $this->assertSame($read->period_start->toDateString(),
            $read->periodFor(RunPeriods::OVERTIME)[0]->toDateString());
    }

    /** Nothing reads these yet: the figures must be identical either way. */
    public function test_storing_a_period_does_not_move_any_figure_in_phase_2(): void
    {
        $before = $this->build();
        $gross  = (float) $before->total_gross;
        $net    = (float) $before->total_net;

        $before->forceFill([
            'overtime_from' => '2026-06-01', 'overtime_to' => '2026-06-30',
        ])->save();

        $after = $this->build();

        $this->assertSame($gross, (float) $after->total_gross);
        $this->assertSame($net, (float) $after->total_net);
    }

    /**
     * A regenerate keeps them. The builder reuses an existing draft's row, so
     * this holds today for free — pinned because Phase 3 must not lose it:
     * approving three more OT claims and pressing Regenerate silently snapping
     * the run back to cycle defaults is the same class of bug the service
     * charge freeze was built to stop.
     */
    public function test_a_regenerate_keeps_the_periods_already_on_the_run(): void
    {
        $run = $this->build();
        $run->forceFill(['service_charge_from' => '2026-08-01', 'service_charge_to' => '2026-08-31'])->save();

        $rebuilt = $this->build();

        $this->assertSame('2026-08-01', $rebuilt->service_charge_from->toDateString());
        $this->assertSame('2026-08-31', $rebuilt->service_charge_to->toDateString());
    }
}
