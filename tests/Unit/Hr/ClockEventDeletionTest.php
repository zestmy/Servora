<?php

namespace Tests\Unit\Hr;

use App\Models\ClockEvent;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\TestCase;

/**
 * Two invariants behind deleting a clock-in, both of which are one line to
 * break and neither of which fails loudly when broken.
 *
 * Dropping SoftDeletes would turn every delete into a destroyed attendance
 * record — and the money would still come out right, because a hard-deleted
 * punch also stops counting, so nothing would look wrong until somebody
 * asked what happened to a punch and found nothing at all.
 *
 * Adding deleted_by to $fillable would let a crafted request name somebody
 * else as the person who removed a payroll record. It is the audit trail for
 * exactly the action most worth auditing, so it is written by the delete
 * path alone.
 */
class ClockEventDeletionTest extends TestCase
{
    public function test_clock_events_soft_delete_rather_than_vanish(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(ClockEvent::class),
            'A punch is an attendance record and a late one is a payroll record. '
            . 'Deleting one must stop it counting without destroying the evidence.'
        );
    }

    public function test_the_deleted_at_column_is_cast_so_it_reads_as_a_date(): void
    {
        // SoftDeletes adds this itself, but a $casts array that overrode
        // $dates without it would quietly turn deleted_at into a string.
        $event = new ClockEvent();

        $this->assertSame('datetime', $event->getCasts()['deleted_at'] ?? null);
    }

    public function test_deleted_by_cannot_be_mass_assigned(): void
    {
        $event = new ClockEvent();

        $this->assertNotContains('deleted_by', $event->getFillable());

        // The stronger claim: filling it does nothing, whatever the list says.
        $event->fill(['deleted_by' => 99]);

        $this->assertNull($event->deleted_by);
    }

    public function test_the_reviewer_and_the_deleter_are_recorded_separately(): void
    {
        // Rejecting a punch and deleting one are different acts by
        // potentially different people; collapsing them onto one column would
        // lose which of the two actually happened.
        $event = new ClockEvent();

        $this->assertContains('reviewed_by', $event->getFillable());
        $this->assertNotContains('deleted_by', $event->getFillable());
    }
}
