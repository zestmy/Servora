<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The payout list pays everyone the POOL pays, not everyone the grid shows.
 *
 * REPORTED AS: three people paid from KLCC's pool were missing from the KLCC
 * payout list. They were correct everywhere else — redirected weeks earlier,
 * present in the frozen calculation at a full share each, listed on the payout
 * report — and absent only from the printed slips.
 *
 * The slips were built from the attendance grid's employee list, which filters
 * on HOME outlet_id and narrows further by the section, employment and search
 * boxes. Somebody posted to HQ and paid from KLCC is not in it, so they got no
 * slip: not a wrong number, no page at all, which is the version nobody
 * notices until the person asks where their money is.
 *
 * The Livewire grid had the same bug and was fixed by giving its panel a
 * pool-scoped query. This pins the export against the same rule, and against
 * the payout report — the two documents answer one question and must not
 * disagree about who is owed what.
 */
class ServiceChargePayoutRedirectedTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $klcc;
    private Outlet $hq;
    private Employee $atKlcc;
    private Employee $redirected;
    private Employee $atHqOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Pool Co', 'slug' => Str::slug('Pool Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->klcc = $this->outlet('KLCC', 'KLCC');
        $this->hq   = $this->outlet('HQ', 'HQ');

        $this->atKlcc     = $this->employee('AISYAH', $this->klcc, null);
        $this->redirected = $this->employee('MOHD AFFANDY', $this->hq, $this->klcc);
        $this->atHqOnly   = $this->employee('BALQIS', $this->hq, null);

        foreach (['hr.attendance', 'hr.attendance.service_charge'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function employee(string $name, Outlet $posted, ?Outlet $paidFrom): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $posted->id,
            'service_charge_outlet_id' => $paidFrom?->id,
            'name' => $name, 'is_active' => true,
            'join_date' => '2025-01-01',
            'service_points_entitlement' => 1.5,
        ]);
    }

    /** @param array<int, int> $outletIds */
    private function manager(array $outletIds): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync($outletIds);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.service_charge']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function pool(float $amount = 30000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->klcc->id,
            'period_from' => '2026-07-26', 'period_to' => '2026-08-25',
            'amount' => $amount, 'retention_percent' => 0,
            'mc_percent' => 5, 'abs_percent' => 10,
        ]);
    }

    /**
     * The slips the payout PDF was built from.
     *
     * Read off the view: dompdf compresses its streams, so the rendered bytes
     * cannot be searched for a name.
     *
     * @return array{names: array<int, string>, sc: array<string, mixed>}
     */
    private function slips(User $user, array $query = []): array
    {
        $captured = null;

        Event::listen('composing: pdf.service-charge-payout', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->actingAs($user)
            ->get(route('hr.attendance.payout-pdf', $query + [
                'from' => '2026-07-26', 'to' => '2026-08-25', 'outlet' => $this->klcc->id,
            ]))
            ->assertOk();

        $this->assertNotNull($captured, 'The payout PDF was never rendered.');

        return [
            'names' => collect($captured['rows'])->map(fn ($r) => $r['employee']->name)->all(),
            'sc'    => $captured['serviceCharge'],
        ];
    }

    public function test_somebody_redirected_into_this_pool_gets_a_slip(): void
    {
        $this->pool();

        $slips = $this->slips($this->manager([$this->klcc->id, $this->hq->id]));

        $this->assertContains('MOHD AFFANDY', $slips['names']);
        $this->assertContains('AISYAH', $slips['names']);
    }

    public function test_somebody_paid_from_their_own_outlet_is_not_dragged_in(): void
    {
        // The OR has to match each person to exactly one pool, or the two
        // pools' divisors stop adding up to the company.
        $this->pool();

        $this->assertNotContains('BALQIS', $this->slips($this->manager([$this->klcc->id, $this->hq->id]))['names']);
    }

    public function test_the_grids_section_and_search_boxes_do_not_narrow_the_payout(): void
    {
        /*
         * The slips are ABOUT the pool. The grid's filters describe what
         * somebody was looking at, and letting them reach the payout means a
         * stray search term silently prints a partial payroll.
         */
        $this->pool();

        $slips = $this->slips($this->manager([$this->klcc->id, $this->hq->id]), ['search' => 'AISYAH']);

        $this->assertContains('MOHD AFFANDY', $slips['names']);
        $this->assertContains('AISYAH', $slips['names']);
    }

    public function test_the_rate_does_not_depend_on_who_pressed_the_button(): void
    {
        /*
         * The divisor used to be ANDed against the exporter's accessible
         * outlets, so a KLCC-only manager dropped redirected staff out of the
         * RM/point base while a manager who could also see HQ kept them — one
         * pool printing two different rates.
         */
        $this->pool();

        $bothOutlets = $this->slips($this->manager([$this->klcc->id, $this->hq->id]))['sc'];
        $klccOnly    = $this->slips($this->manager([$this->klcc->id]))['sc'];

        $this->assertSame(3.0, (float) $bothOutlets['totalPoints']);
        $this->assertSame((float) $bothOutlets['totalPoints'], (float) $klccOnly['totalPoints']);
        $this->assertSame((float) $bothOutlets['perPoint'], (float) $klccOnly['perPoint']);
    }

    public function test_the_payout_list_and_the_payout_report_name_the_same_people(): void
    {
        // Two documents, one question. They are computed by one service now
        // precisely so they cannot drift apart again.
        $this->pool();

        $user = $this->manager([$this->klcc->id, $this->hq->id]);

        $fromReport = collect(app(\App\Services\Hr\ServiceChargeDistribution::class)->forPeriod(
            $this->company->id,
            $user->accessibleOutletIds(),
            \Carbon\Carbon::parse('2026-07-26'),
            \Carbon\Carbon::parse('2026-08-25'),
            $this->klcc->id,
        )['rows'])->map(fn ($r) => $r['employee']->name)->sort()->values()->all();

        $fromSlips = collect($this->slips($user)['names'])->sort()->values()->all();

        $this->assertSame($fromReport, $fromSlips);
    }

    public function test_a_frozen_period_still_pays_the_redirected_person(): void
    {
        /*
         * The reported case: the period was already calculated and the share
         * was sitting in the snapshot the whole time. A frozen distribution is
         * mapped over the employee list it is handed, so the wrong list did
         * not produce a wrong figure — it produced no page.
         */
        $pool = $this->pool();

        app(\App\Services\Hr\ServiceChargeDistribution::class)->freeze(
            $this->company->id,
            [$this->klcc->id, $this->hq->id],
            \Carbon\Carbon::parse('2026-07-26'),
            \Carbon\Carbon::parse('2026-08-25'),
            $this->klcc->id,
            null,
        );

        $this->assertNotNull($pool->fresh()->distribution);

        $slips = $this->slips($this->manager([$this->klcc->id, $this->hq->id]));

        $this->assertContains('MOHD AFFANDY', $slips['names']);
        $this->assertTrue($slips['sc']['frozen'] ?? false);
    }
}
