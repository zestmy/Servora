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
 * The attendance grid and the service charge distribution are two documents.
 *
 * They used to be one: the distribution was appended to the grid whenever the
 * panel was open, so a day-by-day matrix for the whole outlet and a payout
 * table shared a sheet and both came out cramped. They answer different
 * questions and get signed by different people.
 *
 * What matters here is that the split is real on both sides — the grid stops
 * carrying the section, and the distribution can be had without printing the
 * grid to get it — and that the two still look like a matched pair, which is
 * what the shared style and header partials are for.
 */
class AttendanceExportSplitTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Split Co', 'slug' => Str::slug('Split Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        foreach (['hr.attendance', 'hr.attendance.service_charge', 'hr.compensation'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->manager = $this->user(['hr.attendance', 'hr.attendance.service_charge']);

        Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH', 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => 1.5,
        ]);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        if ($abilities) {
            $user->givePermissionTo($abilities);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function pool(): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'amount' => 3000, 'retention_percent' => 0,
        ]);
    }

    /** @return array<string, mixed> the data a view was rendered with */
    private function dataFor(string $view, string $routeName, ?User $user = null): array
    {
        $captured = null;

        Event::listen("composing: {$view}", function ($v) use (&$captured) {
            $captured = $v->getData();
        });

        $this->actingAs($user ?? $this->manager)
            ->get(route($routeName, [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]))
            ->assertOk();

        return $captured ?? [];
    }

    public function test_the_grid_no_longer_carries_the_distribution(): void
    {
        $this->pool();

        $data = $this->dataFor('pdf.attendance', 'hr.attendance.export-pdf');

        $this->assertNotEmpty($data, 'The grid must still render.');
        $this->assertNull($data['serviceCharge'], 'The grid sheet must not carry the pool.');
    }

    public function test_the_grid_does_not_carry_it_even_when_asked_the_old_way(): void
    {
        // Bookmarks and saved links still send service_charge=1. The section
        // has moved; the flag must not bring it back onto this sheet.
        $this->pool();

        $captured = null;
        Event::listen('composing: pdf.attendance', function ($v) use (&$captured) {
            $captured = $v->getData();
        });

        $this->actingAs($this->manager)
            ->get(route('hr.attendance.export-pdf', [
                'from' => '2026-08-01', 'to' => '2026-08-31',
                'outlet' => $this->outlet->id, 'service_charge' => 1,
            ]))
            ->assertOk();

        $this->assertNull($captured['serviceCharge']);
    }

    public function test_the_distribution_downloads_on_its_own(): void
    {
        $this->pool();

        $data = $this->dataFor('pdf.service-charge-distribution', 'hr.attendance.distribution-pdf');

        $this->assertNotEmpty($data, 'The distribution must render as its own document.');
        $this->assertNotNull($data['serviceCharge']);
        $this->assertSame(3000.0, (float) $data['serviceCharge']['row']->amount);
    }

    public function test_the_distribution_refuses_when_no_pool_was_saved(): void
    {
        // A blank sheet headed "Service Charge Distribution" is worse than a
        // refusal — somebody files it.
        $this->actingAs($this->manager)
            ->get(route('hr.attendance.distribution-pdf', [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]))
            ->assertNotFound();
    }

    public function test_the_distribution_needs_the_service_charge_ability(): void
    {
        $this->pool();

        $this->actingAs($this->user(['hr.attendance']))
            ->get(route('hr.attendance.distribution-pdf', [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]))
            ->assertForbidden();
    }

    public function test_the_grid_is_still_readable_without_the_service_charge_ability(): void
    {
        // The two abilities are separate on purpose: somebody who marks
        // attendance does not thereby see what the pool pays.
        $this->pool();

        $this->actingAs($this->user(['hr.attendance']))
            ->get(route('hr.attendance.export-pdf', [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]))
            ->assertOk();
    }

    public function test_both_documents_share_one_stylesheet_and_masthead(): void
    {
        /*
         * They were one document and still have to look like a matched pair.
         * Two copies of the chrome would drift the first time either was
         * touched, so this asserts the partials are actually reached rather
         * than trusting the views to have been written that way.
         */
        $this->pool();

        foreach ([
            ['pdf.attendance', 'hr.attendance.export-pdf'],
            ['pdf.service-charge-distribution', 'hr.attendance.distribution-pdf'],
        ] as [$view, $route]) {
            $rendered = [];

            Event::listen('composing: pdf.partials.attendance-styles', function () use (&$rendered) {
                $rendered['styles'] = true;
            });
            Event::listen('composing: pdf.partials.attendance-header', function ($v) use (&$rendered) {
                $rendered['header'] = $v->getData()['docTitle'] ?? null;
            });

            $this->actingAs($this->manager)
                ->get(route($route, [
                    'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
                ]))
                ->assertOk();

            $this->assertTrue($rendered['styles'] ?? false, "{$view} must use the shared stylesheet.");
            $this->assertNotNull($rendered['header'] ?? null, "{$view} must use the shared masthead.");
        }
    }

    public function test_each_document_names_itself(): void
    {
        $this->pool();

        $titles = [];
        Event::listen('composing: pdf.partials.attendance-header', function ($v) use (&$titles) {
            $titles[] = $v->getData()['docTitle'];
        });

        foreach (['hr.attendance.export-pdf', 'hr.attendance.distribution-pdf'] as $route) {
            $this->actingAs($this->manager)->get(route($route, [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]))->assertOk();
        }

        $this->assertSame(['Attendance Record', 'Service Charge Distribution'], $titles);
    }
}
