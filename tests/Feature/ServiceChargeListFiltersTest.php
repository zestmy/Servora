<?php

namespace Tests\Feature;

use App\Livewire\Hr\ServiceCharge;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Section, employment status and employment type filters on the service
 * charge table — as on Attendance Records — that only HIDE names.
 *
 * Narrowing the list to check one section must never move anybody's money:
 * the pool, its RM/point and the totals are always worked out over everyone
 * it pays, and a saved pool still includes the hidden staff.
 */
class ServiceChargeListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private Section $floor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Filter Co', 'slug' => Str::slug('Filter Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true]);

        $this->kitchen = Section::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true]);
        $this->floor   = Section::create(['company_id' => $this->company->id, 'name' => 'Floor', 'is_active' => true]);

        foreach (['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function staff(string $name, Section $section, string $status = 'confirmed', ?string $type = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'section_id' => $section->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'employment_status' => $status, 'employment_type' => $type,
            'service_points_entitlement' => 10,
            'basic_salary' => 2000, 'pay_type' => 'monthly',
        ]);
    }

    private function panel()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.attendance', 'hr.attendance.record', 'hr.attendance.service_charge']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Livewire::actingAs($user)
            ->test(ServiceCharge::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->set('scAmount', '2000');
    }

    private function visibleNames($component): array
    {
        return $component->viewData('scVisibleRows')->map(fn ($r) => $r['employee']->name)->values()->all();
    }

    public function test_a_section_filter_hides_names_but_not_money(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $this->staff('SITI WAITER', $this->floor);

        // The table appears once the pool is saved.
        $page = $this->panel()->call('saveServiceCharge');
        $before = $page->viewData('serviceCharge')['totals'];

        $page->set('sectionFilter', (string) $this->kitchen->id);

        $this->assertSame(['ALI COOK'], $this->visibleNames($page));
        $this->assertSame(1, $page->viewData('scHiddenCount'));
        $this->assertEquals($before, $page->viewData('serviceCharge')['totals'],
            'Filtering the list must not change the pool totals.');
        $page->assertSee('hidden staff are still in the pool');
    }

    public function test_employment_status_and_type_filters_match_attendance_records(): void
    {
        $this->staff('CONFIRMED STAFF', $this->kitchen, 'confirmed');
        $this->staff('PROBATION STAFF', $this->kitchen, 'probation');
        $this->staff('OUTSOURCED STAFF', $this->kitchen, 'confirmed', Employee::TYPE_OUTSOURCING);

        $page = $this->panel()->set('employmentStatusFilter', 'probation');
        $this->assertSame(['PROBATION STAFF'], $this->visibleNames($page));

        $page->set('employmentStatusFilter', '')->set('employmentTypeFilter', 'exclude_outsourcing');
        $this->assertNotContains('OUTSOURCED STAFF', $this->visibleNames($page));
        $this->assertContains('CONFIRMED STAFF', $this->visibleNames($page));
    }

    public function test_a_pool_saved_while_filtered_still_pays_the_hidden_staff(): void
    {
        $this->staff('ALI COOK', $this->kitchen);
        $hidden = $this->staff('SITI WAITER', $this->floor);

        $this->panel()
            ->set('sectionFilter', (string) $this->kitchen->id)
            ->call('saveServiceCharge')
            ->assertHasNoErrors();

        $pool = ServiceChargePeriod::withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();
        $rows = collect($pool->distribution['rows']);

        $this->assertEquals(1000.0, (float) $rows[(string) $hidden->id]['net'],
            'A filtered-out name is still in the pool and still paid its share.');
        $this->assertSame([], $pool->excludedEmployeeIds());
    }
}
