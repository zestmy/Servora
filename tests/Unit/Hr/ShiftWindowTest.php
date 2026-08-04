<?php

namespace Tests\Unit\Hr;

use App\Models\RosterEntry;
use App\Services\Hr\ShiftResolver;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Turning a roster line into an absolute start and end.
 *
 * The overnight case is the one that matters: get it wrong and a 10pm start
 * looks like a punch 22 hours late.
 */
class ShiftWindowTest extends TestCase
{
    private function entry(string $date, ?string $start, ?string $end): RosterEntry
    {
        $entry = new RosterEntry();
        $entry->day_date    = Carbon::parse($date);
        $entry->shift_start = $start;
        $entry->shift_end   = $end;

        return $entry;
    }

    public function test_a_day_shift_stays_on_its_own_date(): void
    {
        [$start, $end] = (new ShiftResolver())->windowFor(
            $this->entry('2026-08-04', '09:00:00', '17:00:00')
        );

        $this->assertSame('2026-08-04 09:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-04 17:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_an_overnight_shift_ends_the_next_morning(): void
    {
        [$start, $end] = (new ShiftResolver())->windowFor(
            $this->entry('2026-08-04', '22:00:00', '06:00:00')
        );

        $this->assertSame('2026-08-04 22:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-05 06:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_an_end_equal_to_the_start_is_a_full_day_not_a_zero_length_shift(): void
    {
        [$start, $end] = (new ShiftResolver())->windowFor(
            $this->entry('2026-08-04', '09:00:00', '09:00:00')
        );

        $this->assertSame(24.0, $start->diffInHours($end));
    }

    public function test_a_shift_with_no_times_has_no_window(): void
    {
        $this->assertNull((new ShiftResolver())->windowFor(
            $this->entry('2026-08-04', null, null)
        ));
    }

    public function test_an_unparseable_time_yields_no_window_rather_than_throwing(): void
    {
        // A punch must never 500 because a roster row holds junk.
        $this->assertNull((new ShiftResolver())->windowFor(
            $this->entry('2026-08-04', 'not a time', 'nor this')
        ));
    }
}
