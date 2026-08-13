<?php

namespace Tests\Feature;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The service charge list shows everybody the pool pays.
 *
 * REPORTED AS: three people set to be paid from KLCC's pool were not in KLCC's
 * service charge list. They were in the payout report and in the distribution,
 * each taking a share — they were missing from the attendance screen's panel,
 * which is where the pool is actually run from.
 *
 * The panel drew its ROWS from the attendance grid, which selects on home
 * outlet_id, and its DIVISOR from forServiceChargeOutlet(), which selects on
 * pool membership. Redirected staff were therefore in the divisor and absent
 * from the rows: the visible shares stopped adding up to the pool, and the
 * people missing were the ones nobody thinks to check.
 *
 * Employee::forServiceChargeOutlet() names this as the failure the feature can
 * most easily cause. The two lists are built the same way now.
 */
class ServiceChargeRedirectedStaffTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $klcc;
    private Outlet $hq;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Pool Co', 'slug' => Str::slug('Pool Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->klcc = $this->outlet('KLCC', 'KLCC');
        $this->hq   = $this->outlet('HQ', 'HQ');

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->klcc->id, $this->hq->id]);

        setPermissionsTeamId($this->company->id);
        foreach ([
            'hr.view', 'hr.attendance', 'hr.attendance.record',
            'hr.service_charge', 'hr.compensation',
        ] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function employee(string $name, Outlet $home, ?Outlet $paidFrom = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $home->id,
            'service_charge_outlet_id' => $paidFrom?->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => 1.5,
        ]);
    }

    /** The panel's own list, which is what the reported bug was about. */
    private function poolNames(): array
    {
        $c = Livewire::actingAs($this->user)->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->klcc->id);

        $m = new \ReflectionMethod(AttendanceRecords::class, 'serviceChargeEmployees');
        $m->setAccessible(true);

        return $m->invoke($c->instance())->pluck('name')->sort()->values()->all();
    }

    private function divisor(): float
    {
        $c = Livewire::actingAs($this->user)->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->klcc->id);

        $m = new \ReflectionMethod(AttendanceRecords::class, 'serviceChargeTotalPoints');
        $m->setAccessible(true);

        return (float) $m->invoke($c->instance(), []);
    }

    // ── The reported bug ──────────────────────────────────────────────────

    public function test_someone_redirected_into_the_pool_is_on_the_list(): void
    {
        $this->employee('AISYAH AT KLCC', $this->klcc);
        $this->employee('TAUFIQ FROM HQ', $this->hq, $this->klcc);

        $this->assertSame(['AISYAH AT KLCC', 'TAUFIQ FROM HQ'], $this->poolNames(),
            'A person the pool pays belongs on the list the pool is run from.');
    }

    /** The other half of the same rule, or the pool over-allocates. */
    public function test_someone_redirected_out_is_off_the_list(): void
    {
        $this->employee('AISYAH AT KLCC', $this->klcc);
        $this->employee('LEAVING KLCC', $this->klcc, $this->hq);

        $this->assertSame(['AISYAH AT KLCC'], $this->poolNames(),
            'Somebody paid from another pool takes no share here.');
    }

    // ── The property that keeps RM/point honest ───────────────────────────

    /**
     * The rows and the divisor must be the same people. This is the invariant
     * the bug broke: a person counted in one and not the other misprices
     * everybody in the outlet, not only themselves.
     */
    public function test_the_divisor_counts_exactly_the_people_on_the_list(): void
    {
        $this->employee('AISYAH AT KLCC', $this->klcc);
        $this->employee('TAUFIQ FROM HQ', $this->hq, $this->klcc);
        $this->employee('LEAVING KLCC', $this->klcc, $this->hq);
        $this->employee('PLAIN HQ', $this->hq);

        $this->assertSame(['AISYAH AT KLCC', 'TAUFIQ FROM HQ'], $this->poolNames());
        $this->assertEqualsWithDelta(3.0, $this->divisor(), 0.001,
            'Two people at 1.5 points each — the same two the list shows.');
    }

    // ── The grid is a different question ──────────────────────────────────

    /**
     * Attendance is marked for who WORKS here. The two lists were shared,
     * which is how they came to disagree; separating them must not drag the
     * pool's members onto the grid.
     */
    public function test_the_attendance_grid_still_shows_who_works_at_the_outlet(): void
    {
        $this->employee('AISYAH AT KLCC', $this->klcc);
        $this->employee('TAUFIQ FROM HQ', $this->hq, $this->klcc);

        $c = Livewire::actingAs($this->user)->test(AttendanceRecords::class)
            ->set('outletFilter', (string) $this->klcc->id);

        $m = new \ReflectionMethod(AttendanceRecords::class, 'employeesQuery');
        $m->setAccessible(true);

        $this->assertSame(['AISYAH AT KLCC'], $m->invoke($c->instance())->get()->pluck('name')->all(),
            'Somebody posted to HQ is marked present at HQ, not here.');
    }

    /** With no outlet chosen the pool is the company, and everybody is in it. */
    public function test_all_outlets_covers_everybody(): void
    {
        $this->employee('AISYAH AT KLCC', $this->klcc);
        $this->employee('TAUFIQ FROM HQ', $this->hq, $this->klcc);
        $this->employee('PLAIN HQ', $this->hq);

        $c = Livewire::actingAs($this->user)->test(AttendanceRecords::class)->set('outletFilter', '');

        $m = new \ReflectionMethod(AttendanceRecords::class, 'serviceChargeEmployees');
        $m->setAccessible(true);

        $this->assertCount(3, $m->invoke($c->instance()));
    }
}
