<?php

namespace Tests\Feature;

use App\Livewire\Hr\ClockEvents;
use App\Models\ClockEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Who may write off a late charge, and what a waiver leaves behind.
 *
 * The gate is the reason this is a feature test rather than another unit one.
 * `hr.clock` is held by everybody who works the review queue — supervisors,
 * branch managers, anyone who has to clear flagged punches every morning. The
 * ability to decide that money will not be collected was asked for separately
 * and given to two roles, and a check written as `can('hr.clock')` by mistake
 * would hand it to all of them without a single test failing anywhere else.
 *
 * The rest is the invariant the whole design rests on: a waiver forgives the
 * FEE and preserves the FACT. If penalty_amount ever starts coming back zero
 * from this screen, the audit trail is gone and nobody will notice until
 * somebody disputes a month they cannot reconstruct.
 */
class LatenessWaiverScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $this->company = Company::create([
            'name' => 'Waiver Co', 'slug' => Str::slug('Waiver Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Aina', 'is_active' => true,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);

        foreach (['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function latePunch(float $penalty = 9.00): ClockEvent
    {
        return ClockEvent::withoutGlobalScopes()->create([
            'company_id'  => $this->company->id,
            'outlet_id'   => $this->outlet->id,
            'employee_id' => $this->employee->id,
            'type'        => ClockEvent::TYPE_IN,
            'work_date'   => '2026-08-30',
            'happened_at' => '2026-08-30 09:18:00',
            'minutes_late'            => 18,
            'chargeable_late_minutes' => 18,
            'penalty_amount'          => $penalty,
            'status'                  => ClockEvent::STATUS_VERIFIED,
        ]);
    }

    /**
     * The gate. Working the queue is not the same trust as forgiving money.
     */
    public function test_a_user_with_only_hr_clock_cannot_waive(): void
    {
        $event = $this->latePunch();

        Livewire::actingAs($this->user(['hr.clock', 'hr.compensation']))
            ->test(ClockEvents::class)
            ->assertSet('viewingId', null)
            ->call('waiveLateness', $event->id)
            ->assertForbidden();

        $this->assertNull($event->fresh()->lateness_waived_at);
    }

    public function test_an_appointed_user_can_waive_with_a_reason(): void
    {
        $event = $this->latePunch();
        $user  = $this->user(['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation']);

        Livewire::actingAs($user)
            ->test(ClockEvents::class)
            ->call('view', $event->id)
            ->set('waiveReason', 'Roster changed at short notice; told to come in at 10.')
            ->call('waiveLateness', $event->id)
            ->assertHasNoErrors();

        $fresh = $event->fresh();

        $this->assertTrue($fresh->latenessWaived());
        $this->assertSame($user->id, $fresh->lateness_waived_by);
        $this->assertSame('Roster changed at short notice; told to come in at 10.', $fresh->lateness_waive_reason);
        $this->assertSame(0.0, $fresh->chargeableAmount());
    }

    /**
     * The invariant. A waiver forgives the fee and preserves the fact — the
     * punch still says eighteen minutes and still says RM9.00.
     */
    public function test_waiving_does_not_rewrite_the_record(): void
    {
        $event = $this->latePunch();

        Livewire::actingAs($this->user(['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation']))
            ->test(ClockEvents::class)
            ->call('view', $event->id)
            ->set('waiveReason', 'Goodwill, first offence.')
            ->call('waiveLateness', $event->id);

        $fresh = $event->fresh();

        $this->assertSame(18, $fresh->minutes_late);
        $this->assertSame(18, $fresh->chargeable_late_minutes);
        $this->assertSame('9.00', (string) $fresh->penalty_amount);
    }

    /** A waiver with no reason is a mis-click nobody can tell apart later. */
    public function test_a_waiver_needs_a_reason(): void
    {
        $event = $this->latePunch();

        Livewire::actingAs($this->user(['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation']))
            ->test(ClockEvents::class)
            ->call('view', $event->id)
            ->set('waiveReason', '   ')
            ->call('waiveLateness', $event->id)
            ->assertHasErrors('waiveReason');

        $this->assertNull($event->fresh()->lateness_waived_at);
    }

    /** Nothing to forgive on a punctual punch. */
    public function test_a_punch_with_no_charge_cannot_be_waived(): void
    {
        $event = $this->latePunch(penalty: 0.0);

        Livewire::actingAs($this->user(['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation']))
            ->test(ClockEvents::class)
            ->call('view', $event->id)
            ->set('waiveReason', 'Nothing here.')
            ->call('waiveLateness', $event->id);

        $this->assertNull($event->fresh()->lateness_waived_at);
    }

    /**
     * Putting it back restores the ORIGINAL charge, not a re-priced one.
     *
     * The payoff for never having overwritten penalty_amount: a rate change
     * between the waiver and the reversal cannot quietly move the figure.
     */
    public function test_restoring_brings_back_the_original_charge(): void
    {
        $event = $this->latePunch();
        $user  = $this->user(['hr.clock', 'hr.clock.waive_lateness', 'hr.compensation']);

        Livewire::actingAs($user)
            ->test(ClockEvents::class)
            ->call('view', $event->id)
            ->set('waiveReason', 'Waived in error.')
            ->call('waiveLateness', $event->id)
            ->call('restoreLatenessCharge', $event->id);

        $fresh = $event->fresh();

        $this->assertFalse($fresh->latenessWaived());
        $this->assertNull($fresh->lateness_waived_by);
        $this->assertNull($fresh->lateness_waive_reason);
        $this->assertSame(9.00, $fresh->chargeableAmount());
    }

    /** Restoring is the same trust as waiving, and gated the same way. */
    public function test_a_user_without_the_ability_cannot_restore_a_charge(): void
    {
        $event = $this->latePunch();

        $event->forceFill([
            'lateness_waived_at'    => now(),
            'lateness_waived_by'    => $this->user(['hr.clock', 'hr.clock.waive_lateness'])->id,
            'lateness_waive_reason' => 'Already forgiven.',
        ])->save();

        Livewire::actingAs($this->user(['hr.clock', 'hr.compensation']))
            ->test(ClockEvents::class)
            ->call('restoreLatenessCharge', $event->id)
            ->assertForbidden();

        $this->assertTrue($event->fresh()->latenessWaived());
    }
}
