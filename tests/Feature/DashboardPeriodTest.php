<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Support\CostVerdict;
use App\Support\DashboardPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's reporting window, and the window it measures against.
 *
 * There was no coverage of the dashboard at all, which is how forty-four tiles
 * shipped without a baseline and how every builder stayed hardwired to the
 * current calendar month. What is pinned here is the arithmetic a reader
 * cannot check for themselves — if the prior window is wrong, every delta on
 * every one of the seven dashboards is quietly wrong with it, and nothing on
 * screen would say so.
 */
class DashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name' => 'Window Co', 'slug' => Str::slug('Window Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        $this->actingAs($this->user);
    }

    /**
     * The comparison a month-to-date figure needs.
     *
     * The obvious rule — "the same number of days, immediately before" — is
     * wrong here and wrong in a way nobody would notice: on the 12th it slides
     * back across the month boundary and measures 12 days of August against 5
     * of August and 7 of July. A month compares against the matching stretch
     * of the month before.
     */
    public function test_a_month_in_progress_compares_against_the_same_days_of_last_month(): void
    {
        $this->travelTo(Carbon::parse('2026-08-12'));

        $window = DashboardPeriod::make('this_month');

        $this->assertSame('2026-08-01', $window->start->toDateString());
        $this->assertSame('2026-08-12', $window->end->toDateString(), 'a month in progress ends today, not at month end');
        $this->assertSame('2026-07-01', $window->priorStart->toDateString());
        $this->assertSame('2026-07-12', $window->priorEnd->toDateString());

        $this->travelBack();
    }

    /**
     * A short month has no 31st, and asking for one rolls into the next month.
     * February is where this breaks if it is going to.
     */
    public function test_the_prior_window_is_clamped_to_the_end_of_a_shorter_month(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31'));

        $window = DashboardPeriod::make('this_month');

        $this->assertSame('2026-02-01', $window->priorStart->toDateString());
        $this->assertSame(
            '2026-02-28',
            $window->priorEnd->toDateString(),
            'the prior window must stop at the end of February, not run into March'
        );

        $this->travelBack();
    }

    /**
     * Carbon 3 returns a FLOAT from diffInDays, and endOfMonth() lands at
     * 23:59:59.999 — so `diffInDays() + 1` produced 31.999999999988 and was
     * then implicitly truncated by the int-typed property. It happened to land
     * on the right number, which is the worst kind of correct: one promoted
     * property away from reporting a 30-day month as 29 days and taking every
     * run-rate projection with it.
     *
     * @dataProvider wholeMonths
     */
    public function test_a_complete_month_counts_its_own_length_exactly(string $today, int $expected): void
    {
        $this->travelTo(Carbon::parse($today));

        $window = DashboardPeriod::make('last_month');

        $this->assertSame($expected, $window->daysElapsed);
        $this->assertSame($expected, $window->daysInPeriod);
        $this->assertFalse($window->isPartial(), 'a finished month is not partial');
        $this->assertNull($window->runRate(1000), 'a finished month needs no projection');

        $this->travelBack();
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function wholeMonths(): array
    {
        return [
            '31-day July from August'    => ['2026-08-12', 31],
            '30-day June from July'      => ['2026-07-05', 30],
            '28-day February from March' => ['2026-03-05', 28],
        ];
    }

    /**
     * A rolling range is the one case where "equal length, immediately before"
     * IS the right rule, and the two windows must not overlap by a day.
     */
    public function test_a_rolling_range_compares_against_the_window_behind_it(): void
    {
        $this->travelTo(Carbon::parse('2026-08-12'));

        $window = DashboardPeriod::make('last_7');

        $this->assertSame('2026-08-06', $window->start->toDateString());
        $this->assertSame('2026-08-12', $window->end->toDateString());
        $this->assertSame(7, $window->daysElapsed);

        $this->assertSame('2026-07-30', $window->priorStart->toDateString());
        $this->assertSame('2026-08-05', $window->priorEnd->toDateString());
        $this->assertTrue(
            $window->priorEnd->lessThan($window->start),
            'the prior window must not overlap the reporting window'
        );

        $this->travelBack();
    }

    /** A tampered query string must never widen what is shown. */
    public function test_an_unknown_period_falls_back_to_the_current_month(): void
    {
        $this->assertSame('this_month', DashboardPeriod::make('../../etc/passwd')->key);
        $this->assertSame('this_month', DashboardPeriod::make('')->key);
        $this->assertSame('this_month', DashboardPeriod::make(null)->key);
    }

    /**
     * Only a calendar month can be costed with stock movement folded in —
     * CostSummaryService skips stock takes for a custom range. A rolling window
     * that claimed otherwise would put a purchase-based cost percentage
     * side by side with a stock-adjusted one as though they measured the same
     * thing.
     */
    public function test_only_month_windows_claim_stock_adjusted_costing(): void
    {
        $this->assertTrue(DashboardPeriod::make('this_month')->usesMonthlyCosting());
        $this->assertTrue(DashboardPeriod::make('last_month')->usesMonthlyCosting());
        $this->assertFalse(DashboardPeriod::make('last_7')->usesMonthlyCosting());
        $this->assertFalse(DashboardPeriod::make('last_30')->usesMonthlyCosting());
    }

    public function test_the_run_rate_projects_month_to_date_onto_the_whole_month(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10'));

        // 10 of 31 days, so 1,000 so far projects to 3,100.
        $this->assertSame(3100.0, round(DashboardPeriod::make('this_month')->runRate(1000), 1));

        $this->travelBack();
    }

    /**
     * The thresholds were five independent literals across five files that
     * happened to agree. One definition now, and the boundaries are exclusive
     * — exactly 35% is "near", not "over".
     */
    public function test_the_cost_verdict_reads_from_one_set_of_thresholds(): void
    {
        config(['costing.target.over' => 35, 'costing.target.near' => 30]);

        $this->assertSame('on target',   CostVerdict::for(29.9)['word']);
        $this->assertSame('on target',   CostVerdict::for(30.0)['word']);
        $this->assertSame('near target', CostVerdict::for(30.1)['word']);
        $this->assertSame('near target', CostVerdict::for(35.0)['word']);
        $this->assertSame('over target', CostVerdict::for(35.1)['word']);

        // Moving the config moves every gauge and the COGS table together.
        config(['costing.target.over' => 25, 'costing.target.near' => 20]);
        $this->assertSame('over target', CostVerdict::for(30.0)['word']);
    }

    /**
     * A percentage off four days of trading was painted red or green like a
     * finding, so a slow first weekend looked like a crisis every month.
     */
    public function test_a_cost_percentage_carries_no_verdict_until_enough_days_have_passed(): void
    {
        config(['costing.min_days_for_verdict' => 5]);

        $this->assertFalse(CostVerdict::isReady(4));
        $this->assertTrue(CostVerdict::isReady(5));
        $this->assertSame('too early to call', CostVerdict::pending()['word']);
    }

    /**
     * Every offered period must actually render. The seven role arms differ in
     * which figures they build, but the period plumbing is shared — so a
     * window that breaks one breaks all of them.
     *
     * @dataProvider periods
     */
    public function test_the_dashboard_renders_for_every_offered_period(string $period): void
    {
        Livewire::actingAs($this->user)
            ->test(Dashboard::class, ['period' => $period])
            ->assertOk()
            ->assertSet('period', $period);
    }

    /** @return array<string, array{0: string}> */
    public static function periods(): array
    {
        return collect(array_keys(DashboardPeriod::options()))
            ->mapWithKeys(fn ($key) => [$key => [$key]])
            ->all();
    }

    /**
     * The layout emits no h1 of its own and the dashboard opened with an h2, so
     * the document had no root heading and every card title below it hung off
     * nothing.
     */
    public function test_the_dashboard_has_a_single_root_heading(): void
    {
        $html = Livewire::actingAs($this->user)->test(Dashboard::class)->html();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1 on the page');
        $this->assertStringContainsString('Dashboard</h1>', $html, 'the h1 names the page, not the reader');
    }
}
