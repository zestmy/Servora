<?php

namespace Tests\Unit\Hr;

use App\Livewire\Hr\ClockSettings;
use App\Models\ClockEvent;
use PHPUnit\Framework\TestCase;

/**
 * Which flags send a punch to a manager, now that a company gets to decide.
 *
 * The routing is one array_diff. What needs guarding is the surrounding
 * agreement, because each half of it fails silently:
 *
 *   the screen must offer every flag  a flag with no group is un-tickable, and
 *                                     the save writes it into the skip list for
 *                                     everybody. A check that quietly stops
 *                                     being made looks exactly like a check
 *                                     that is passing.
 *   null must mean the default        every existing company has null in this
 *                                     column, and if that read as "skip
 *                                     nothing" they would all wake up to a
 *                                     review queue holding every late punch.
 *   an empty array must not           it is a real answer meaning "ask me about
 *                                     everything", and collapsing it back to
 *                                     the default would make that unsavable.
 */
class ReviewPolicyTest extends TestCase
{
    /**
     * Every flag the app can raise is offered on the settings screen.
     *
     * The one that protects a future release: adding a flag to FLAG_LABELS
     * without adding it to a group would auto-approve it for every company on
     * their next save, and nothing else would say so.
     */
    public function test_the_policy_groups_cover_every_flag(): void
    {
        $grouped = ClockSettings::policyFlags();
        $known   = array_keys(ClockEvent::FLAG_LABELS);

        sort($grouped);
        sort($known);

        $this->assertSame($known, $grouped, 'Every flag in FLAG_LABELS needs a REVIEW_POLICY_GROUPS entry.');
    }

    /** No flag may sit in two groups — the screen would draw it twice. */
    public function test_no_flag_is_grouped_twice(): void
    {
        $grouped = ClockSettings::policyFlags();

        $this->assertSame(count($grouped), count(array_unique($grouped)));
    }

    /** The shipped default: the four that are a record, not a decision. */
    public function test_the_default_skips_only_the_non_decisions(): void
    {
        $this->assertSame(
            ['late', 'no_shift', 'kiosk_down', 'no_outlet_fence'],
            ClockEvent::DEFAULT_AUTO_APPROVE_FLAGS,
        );
    }

    /**
     * An outlet with no coordinates set does not queue every punch it takes.
     *
     * The gap is in the outlet's configuration and identical on every punch
     * made there, so no decision a manager takes on one of them changes
     * anything — but the punch still records within_geofence false, so nothing
     * reads as "everyone was on site".
     */
    public function test_an_unconfigured_outlet_does_not_reach_the_queue(): void
    {
        $this->assertNotContains(
            'no_outlet_fence',
            ClockEvent::reviewableFlags(['no_outlet_fence']),
        );
    }

    /** Lateness is charged, not adjudicated — it never queued and still does not. */
    public function test_lateness_alone_does_not_reach_the_queue(): void
    {
        $this->assertSame([], ClockEvent::reviewableFlags(['late', 'no_shift']));
    }

    /** A real problem still does, and arrives on its own. */
    public function test_a_mismatched_face_still_reaches_the_queue(): void
    {
        $this->assertSame(
            ['face_mismatch'],
            ClockEvent::reviewableFlags(['late', 'face_mismatch', 'no_shift']),
        );
    }

    /**
     * Null is "never configured", and every existing row holds it.
     *
     * If it read as an empty skip list, every company in the system would find
     * every late punch and every unrostered punch waiting for approval the
     * morning this deployed.
     */
    public function test_an_unconfigured_company_gets_the_shipped_default(): void
    {
        $settings = new \App\Models\ClockSetting(['auto_approve_flags' => null]);

        $this->assertSame(ClockEvent::DEFAULT_AUTO_APPROVE_FLAGS, $settings->autoApproveFlags());
    }

    /** An empty array is a real answer — "ask me about everything" — and stands. */
    public function test_an_empty_policy_reviews_everything(): void
    {
        $settings = new \App\Models\ClockSetting(['auto_approve_flags' => []]);

        $this->assertSame([], $settings->autoApproveFlags());
        $this->assertTrue($settings->sendsToReview('late'));
    }

    /** A flag retired in a later release cannot linger in a saved policy. */
    public function test_unknown_flags_are_dropped_from_a_saved_policy(): void
    {
        $settings = new \App\Models\ClockSetting([
            'auto_approve_flags' => ['late', 'a_flag_that_no_longer_exists'],
        ]);

        $this->assertSame(['late'], $settings->autoApproveFlags());
    }

    /**
     * The inversion the settings screen performs on save.
     *
     * Held here rather than only in the component because it is the step that
     * decides what gets written, and getting it backwards would store the
     * review list in a column read as the skip list — which reviews exactly
     * the flags the manager just switched off.
     */
    public function test_saving_stores_the_complement_of_what_was_ticked(): void
    {
        $ticked = ['face_mismatch', 'duplicate'];

        $stored = array_values(array_diff(ClockSettings::policyFlags(), $ticked));

        $this->assertNotContains('face_mismatch', $stored);
        $this->assertNotContains('duplicate', $stored);
        $this->assertContains('late', $stored);
        $this->assertContains('outside_geofence', $stored);
    }
}
