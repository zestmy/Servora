<?php

namespace Tests\Unit\Hr;

use App\Models\ClockSetting;
use PHPUnit\Framework\TestCase;

/**
 * How much noise the clock is allowed to make.
 *
 * Three values rather than a switch, and the middle one is the reason. A face
 * scan gives nothing to feel — you touch nothing — so a screen with no sound at
 * all leaves somebody looking at the camera with no way to know it saw them.
 * Collapsing this to on/off would force a room that finds the chime intrusive
 * to choose between it and no feedback whatsoever.
 *
 * The fallback is the part worth pinning. Every existing company has no value
 * in this column, and if that read as silence then a release would mute every
 * kiosk in the product without anybody choosing to.
 */
class ClockSoundModeTest extends TestCase
{
    private function settings(mixed $mode): ClockSetting
    {
        return new ClockSetting(['sound_mode' => $mode]);
    }

    public function test_the_three_modes_are_offered(): void
    {
        $this->assertSame(['full', 'chirp', 'off'], array_keys(ClockSetting::SOUND_MODES));
    }

    public function test_each_mode_reads_back_as_itself(): void
    {
        foreach (array_keys(ClockSetting::SOUND_MODES) as $mode) {
            $this->assertSame($mode, $this->settings($mode)->soundMode());
        }
    }

    /** Never configured is not the same as chosen silence. */
    public function test_an_unset_mode_falls_back_to_full(): void
    {
        $this->assertSame('full', $this->settings(null)->soundMode());
    }

    /**
     * A value this release does not recognise falls back to full rather than
     * reaching the page, where it would render as an attribute beep.js does not
     * know and be treated as full anyway — but one layer later and untested.
     */
    public function test_an_unknown_mode_falls_back_to_full(): void
    {
        $this->assertSame('full', $this->settings('loud')->soundMode());
        $this->assertSame('full', $this->settings('')->soundMode());
    }

    /**
     * The quiz is not on this list.
     *
     * A guard against somebody later "tidying" the two sound systems into one:
     * the labels here all describe a punch, and none of them mentions training,
     * because a company silencing its counter tablet must not silence a
     * trainee's own phone.
     */
    public function test_the_modes_describe_punches_and_not_the_quiz(): void
    {
        $labels = strtolower(implode(' ', ClockSetting::SOUND_MODES));

        $this->assertStringNotContainsString('quiz', $labels);
        $this->assertStringNotContainsString('training', $labels);
        $this->assertStringContainsString('punch', $labels);
    }
}
