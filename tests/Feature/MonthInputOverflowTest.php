<?php

namespace Tests\Feature;

use App\Livewire\Hr\Payroll;
use App\Models\CompensationSetting;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\StatutorySetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A "YYYY-MM" month input must not depend on what day it is read.
 *
 * Carbon::createFromFormat fills every field the format does not name from NOW.
 * A plain 'Y-m' therefore takes today's DAY-OF-MONTH, so asking for "2026-09"
 * on the 31st builds 31 September — a date that does not exist, which PHP
 * overflows into 1 October. startOfMonth() then lands on a month nobody chose,
 * and with a 26th–25th pay cycle the payroll form seeds 26 Sep – 25 Oct in
 * place of 26 Aug – 25 Sep.
 *
 * IT ONLY MISFIRES ON THREE DAYS A MONTH — when today's day-of-month exceeds
 * the length of the month being asked for — and those three days are month-end,
 * which is exactly when payroll gets run. That is why it survived in six places
 * for months, and why the test that caught it did so by accident: nothing in
 * that file pins the clock, so it passed every day until somebody ran it on the
 * 31st.
 *
 * So this file pins the clock deliberately. `Carbon::setTestNow` to the 31st is
 * the entire point — without it these assertions pass on the 1st whether the
 * code is right or not.
 */
class MonthInputOverflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The 31st of a 31-day month, chosen so the NEXT month is 30 days long.
         * Any date whose day-of-month exceeds the target month's length would
         * do; this is the shape the bug was reported in.
         */
        Carbon::setTestNow(Carbon::parse('2026-08-31 09:00:00'));

        $this->company = Company::create([
            'name' => 'Overflow Co', 'slug' => Str::slug('Overflow Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => false, 'socso_enabled' => false,
            'eis_enabled' => false, 'pcb_enabled' => false])->save();

        // 26th–25th, which is what turns a one-month slip into the wrong dates
        // rather than into an obviously wrong month.
        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->payroll_cycle_start_day = 26;
        $c->save();

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.payroll', 'hr.payroll.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.payroll', 'hr.payroll.approve']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function form()
    {
        return Livewire::actingAs($this->user)
            ->test(Payroll::class)
            ->set('showNew', true)
            ->set('newOutlet', (string) $this->outlet->id);
    }

    /**
     * The cycle the form resolves for a month, as two date strings.
     *
     * Read off the component itself rather than off newFrom/newTo: those two
     * are the whole-run OVERRIDE and stay empty until somebody turns it on, so
     * asserting them would prove only that an unused field is unused. This is
     * the method every period on the screen is derived from.
     *
     * @return array{0: string, 1: string}
     */
    private function cycleFor(string $month): array
    {
        $range = $this->form()->set('newMonth', $month)->instance()->newMonthRange();

        $this->assertNotNull($range, "No cycle resolved for {$month}.");

        return [$range[0]->toDateString(), $range[1]->toDateString()];
    }

    /**
     * September, asked for on the 31st of August.
     *
     * The failure this pins is not an exception. It is a form that quietly
     * offers the wrong month's dates, on the day of the month when somebody is
     * most likely to be generating a run.
     */
    public function test_a_thirty_day_month_read_on_the_thirty_first_keeps_its_own_cycle(): void
    {
        $this->assertSame(['2026-08-26', '2026-09-25'], $this->cycleFor('2026-09'));
    }

    /** The same, through the per-component custom range that first caught it. */
    public function test_a_custom_component_range_reseeds_to_the_right_month(): void
    {
        $this->form()
            ->set('newMonth', '2026-08')
            ->set('periodMode.attendance', Payroll::MODE_CUSTOM)
            ->set('newMonth', '2026-09')
            ->assertSet('periodDates.attendance.from', '2026-08-26')
            ->assertSet('periodDates.attendance.to', '2026-09-25');
    }

    /** February is the worst case: three days of the month overflow it by two. */
    public function test_february_read_on_the_thirty_first_keeps_its_own_cycle(): void
    {
        $this->assertSame(['2027-01-26', '2027-02-25'], $this->cycleFor('2027-02'));
    }

    /** A 31-day month cannot overflow, so this passes either way — the control. */
    public function test_a_thirty_one_day_month_is_unaffected(): void
    {
        $this->assertSame(['2026-09-26', '2026-10-25'], $this->cycleFor('2026-10'));
    }

    /**
     * No source file may parse a month with the unsafe format again.
     *
     * The behavioural tests above cover the payroll form; this covers the other
     * five sites and, more to the point, the sixth one somebody adds next year.
     * A screen that reads a month input is easy to write and the trap is
     * invisible on 28 days out of 31 — a reviewer will not catch it, so the
     * suite has to.
     *
     * `!Y-m` resets the unnamed fields to their defaults instead of to today.
     * It is already what the other two dozen month inputs in the product use.
     */
    public function test_no_source_file_parses_a_month_without_the_reset(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            // The bang is what makes it safe. Anything else reads the day,
            // and on some days the month, from the clock.
            if (str_contains($contents, "createFromFormat('Y-m'")) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, "Use createFromFormat('!Y-m', …) — without the bang, "
            . "the day comes from today and a 30-day month overflows on the 31st.");
    }
}
