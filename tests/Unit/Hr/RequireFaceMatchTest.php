<?php

namespace Tests\Unit\Hr;

use App\Models\ClockSetting;
use PHPUnit\Framework\TestCase;

/**
 * The decision table behind refusing a mismatched face.
 *
 * The refusal itself is one `if`. What needs guarding is everything it must
 * NOT refuse, because each exemption is invisible until the day it is missing
 * and somebody is standing outside unable to start their shift:
 *
 *   not enrolled   nothing to match against, so nothing can fail. Refusing
 *                  here would lock out every employee a manager has not got
 *                  round to enrolling — which, today, is nearly all of them.
 *   break punches  lenient throughout. A camera that fails must not strand
 *                  somebody mid-shift, and ending a break is not a punch you
 *                  can decline to make.
 *   clock-out      refusing one is a trap with no way out: the shift stays
 *                  open, so the next action is always another clock-out, so
 *                  it is refused again, for ever.
 *   setting off    the default, and the previous behaviour: record it, flag
 *                  it, let a manager look.
 *
 * ClockInService needs a database, so this tests the predicate rather than
 * the service. It is a faithful copy of the condition — and the test that
 * follows checks the real source still reads that way, so the copy cannot
 * drift into being a test of itself.
 */
class RequireFaceMatchTest extends TestCase
{
    /** The rule as ClockInService applies it. */
    private function refuses(
        bool $requireMatch,
        bool $verified,
        bool $enrolled,
        bool $lenient,
        string $type = 'in',
    ): bool {
        // A punch with nothing enrolled never reaches the mismatch branch:
        // assessFace() returns at the not_enrolled guard above it.
        if (! $enrolled) {
            return false;
        }

        return ! $verified && $requireMatch && ! $lenient && $type === 'in';
    }

    public function test_a_mismatch_is_refused_when_the_company_asks_for_it(): void
    {
        $this->assertTrue($this->refuses(true, false, true, false));
    }

    public function test_a_matching_face_is_never_refused(): void
    {
        $this->assertFalse($this->refuses(true, true, true, false));
    }

    public function test_a_mismatch_is_only_flagged_when_the_setting_is_off(): void
    {
        $this->assertFalse($this->refuses(false, false, true, false));
    }

    public function test_somebody_with_no_enrolled_face_is_never_refused(): void
    {
        // The one that would hurt most. Turning the setting on must not lock
        // out staff who have simply not been enrolled yet.
        $this->assertFalse($this->refuses(true, false, false, false));
        $this->assertFalse($this->refuses(true, true, false, false));
    }

    public function test_break_punches_are_never_refused_for_a_mismatch(): void
    {
        $this->assertFalse($this->refuses(true, false, true, true, 'break_start'));
        $this->assertFalse($this->refuses(true, false, true, true, 'break_end'));
    }

    public function test_a_clock_out_is_never_refused_for_a_mismatch(): void
    {
        // Refusing one is a trap with no way out: the shift stays open, so
        // nextType() keeps answering "out", so every retry is another refused
        // clock-out — and the person can never clock in again either. Unlike
        // a refused clock-in there is nothing the employee can do about it.
        $this->assertFalse($this->refuses(true, false, true, false, 'out'));
    }

    public function test_the_setting_defaults_to_off(): void
    {
        // Every other enforcement switch here defaults on. This one cannot,
        // because it is the only one that can send somebody home, and the
        // enrolment data on production already contains a capture that would
        // refuse its own owner.
        $this->assertFalse((new ClockSetting())->require_face_match ?? false);
        $this->assertContains('require_face_match', (new ClockSetting())->getFillable());
        $this->assertSame('boolean', (new ClockSetting())->getCasts()['require_face_match'] ?? null);
    }

    public function test_the_service_still_applies_the_rule_this_file_models(): void
    {
        // Guards against the copy above drifting from the real condition.
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/Hr/ClockInService.php');

        $this->assertStringContainsString(
            'if ($settings->require_face_match && ! $lenient && $type === ClockEvent::TYPE_IN) {',
            $source,
            'The refusal condition changed shape; update the model in this test to match.'
        );

        // And that it still sits AFTER the not-enrolled guards, which is what
        // makes the exemption real rather than assumed.
        $notEnrolled = strpos($source, "\$flags[] = 'not_enrolled';");
        $refusal     = strpos($source, 'require_face_match && ! $lenient');

        $this->assertIsInt($notEnrolled);
        $this->assertIsInt($refusal);
        $this->assertLessThan(
            $refusal,
            $notEnrolled,
            'The mismatch refusal must come after the not-enrolled guards, or staff with '
            . 'no face on file would be turned away.'
        );
    }
}
