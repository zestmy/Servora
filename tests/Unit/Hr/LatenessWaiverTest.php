<?php

namespace Tests\Unit\Hr;

use App\Models\ClockEvent;
use App\Services\Hr\LatePenalties;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Waiving a late charge, and the line between what is RECORDED and what is
 * COLLECTED.
 *
 * The waiver is three nullable columns and one `if`. What needs guarding is
 * the separation on either side of it, because both halves fail silently:
 *
 *   the record must survive   penalty_amount is never rewritten, so a punch
 *                             waived in August still says in December that the
 *                             person was eighteen minutes late and that it
 *                             would have cost RM9.00. Zeroing the column would
 *                             make an act of discretion indistinguishable from
 *                             a punctual arrival, which is the one question
 *                             anybody auditing waivers is asking.
 *
 *   the money must not        LatePenalties feeds the service charge
 *                             distribution. A waiver that failed to reach it
 *                             would show "waived" on every screen while the
 *                             deduction went on happening, and nobody would
 *                             find out until an employee counted their own
 *                             service charge.
 *
 * The minutes deliberately survive into the totals alongside a zero amount:
 * somebody was late, and a report reading "45 min late, RM0.00" says both what
 * happened and what was decided about it.
 */
class LatenessWaiverTest extends TestCase
{
    /** An unsaved event — reduce() never touches the database. */
    private function event(
        int $employeeId,
        string $workDate,
        int $late,
        float $amount,
        bool $waived = false,
    ): ClockEvent {
        $event = new ClockEvent();

        $event->employee_id             = $employeeId;
        $event->work_date               = Carbon::parse($workDate);
        $event->type                    = ClockEvent::TYPE_IN;
        $event->chargeable_late_minutes = $late;
        $event->penalty_amount          = $amount;
        $event->lateness_waived_at      = $waived ? Carbon::parse('2026-08-31 09:00:00') : null;

        return $event;
    }

    public function test_a_punch_is_not_waived_by_default(): void
    {
        $event = $this->event(1, '2026-08-04', 18, 9.00);

        $this->assertFalse($event->latenessWaived());
        $this->assertSame(9.00, $event->chargeableAmount());
    }

    public function test_waiving_makes_the_charge_nil(): void
    {
        $event = $this->event(1, '2026-08-04', 18, 9.00, waived: true);

        $this->assertTrue($event->latenessWaived());
        $this->assertSame(0.0, $event->chargeableAmount());
    }

    /** The whole point: forgiving the fee does not erase the lateness. */
    public function test_waiving_leaves_the_record_intact(): void
    {
        $event = $this->event(1, '2026-08-04', 18, 9.00, waived: true);

        $this->assertSame(18, $event->chargeable_late_minutes);
        $this->assertSame('9.00', (string) $event->penalty_amount);
    }

    /** Nothing to forgive on a punctual punch, so nothing to offer. */
    public function test_a_punch_with_no_charge_has_nothing_to_waive(): void
    {
        $this->assertFalse($this->event(1, '2026-08-04', 0, 0.0)->hasLatenessCharge());
        $this->assertTrue($this->event(1, '2026-08-04', 18, 9.00)->hasLatenessCharge());
    }

    /**
     * The money path. This is the assertion that matters — LatePenalties is
     * what the service charge distribution deducts from.
     */
    public function test_a_waived_charge_is_not_collected(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 18, 9.00, waived: true),
        ]));

        $this->assertSame(0.0, $totals[1]['amount']);
    }

    /** ...and the lateness still shows, so the waiver is visible in a report. */
    public function test_a_waived_charge_keeps_its_minutes(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 45, 22.50, waived: true),
        ]));

        $this->assertSame(45, $totals[1]['minutes']);
        $this->assertSame(1, $totals[1]['shifts']);
    }

    /** One waiver forgives one shift, not the week. */
    public function test_waiving_one_shift_leaves_the_others_charged(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 18, 9.00, waived: true),
            $this->event(1, '2026-08-05', 10, 5.00),
            $this->event(1, '2026-08-06', 6, 3.00),
        ]));

        $this->assertSame(8.00, $totals[1]['amount']);
        $this->assertSame(34, $totals[1]['minutes']);
        $this->assertSame(3, $totals[1]['shifts']);
    }

    /** And it forgives one person, not everybody late that morning. */
    public function test_waiving_one_employee_does_not_touch_another(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 18, 9.00, waived: true),
            $this->event(2, '2026-08-04', 18, 9.00),
        ]));

        $this->assertSame(0.0, $totals[1]['amount']);
        $this->assertSame(9.00, $totals[2]['amount']);
    }

    /**
     * The first punch of a shift still wins, waived or not.
     *
     * Otherwise waiving the 8:05 punch would silently promote the 8:20 one and
     * charge MORE than before the waiver — the exact opposite of what the
     * manager pressed the button for.
     */
    public function test_waiving_the_first_punch_does_not_promote_the_second(): void
    {
        $totals = LatePenalties::reduce(new Collection([
            $this->event(1, '2026-08-04', 5, 2.50, waived: true),
            $this->event(1, '2026-08-04', 20, 10.00),
        ]));

        $this->assertSame(0.0, $totals[1]['amount']);
    }
}
