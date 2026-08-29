<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Models\User;
use App\Services\Hr\ServiceChargeDistribution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The service charge distribution as a spreadsheet.
 *
 * The same document as the distribution PDF, built from the same gather(), on
 * the same ability and with the same refusal when no pool exists. What it adds
 * is what a sheet can hold and a page cannot: a Status column saying in words
 * why a share is nil, and the pool's own arithmetic underneath.
 *
 * The file is opened and read back rather than merely downloaded. A 200 with a
 * spreadsheet MIME type proves the route works; it proves nothing about which
 * column a figure landed in, and a column shifted by one reads as wrong money
 * rather than as a broken export.
 */
class ServiceChargeDistributionExcelTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Sheet Co', 'slug' => Str::slug('Sheet Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        foreach (['hr.attendance', 'hr.attendance.service_charge'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->manager = $this->user(['hr.attendance', 'hr.attendance.service_charge']);
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

    private function staff(string $name, float $points): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'service_points_entitlement' => $points,
        ]);
    }

    private function pool(float $amount = 3000): ServiceChargePeriod
    {
        return ServiceChargePeriod::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'amount' => $amount, 'retention_percent' => 0, 'mc_percent' => 0, 'abs_percent' => 0,
        ]);
    }

    private function download(?User $user = null)
    {
        return $this->actingAs($user ?? $this->manager)
            ->get(route('hr.attendance.distribution-excel', [
                'from' => '2026-08-01', 'to' => '2026-08-31', 'outlet' => $this->outlet->id,
            ]));
    }

    /** @return array<int, array<int, mixed>> the sheet, read back as a grid */
    private function grid($response): array
    {
        // Read off the response's own file, the way the other Excel tests do.
        // streamedContent() is empty here: this is a BinaryFileResponse, not a
        // streamed one, and the file is deleted after send.
        return IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet()
            ->toArray(null, true, false, false);
    }

    /** @return array<int, string> the cells of the row whose first cell matches */
    private function rowStartingWith(array $grid, string $first): ?array
    {
        foreach ($grid as $row) {
            if (trim((string) ($row[0] ?? '')) === $first) {
                return $row;
            }
        }

        return null;
    }

    public function test_the_sheet_downloads(): void
    {
        $this->pool();
        $this->staff('AISYAH', 1.5);

        $this->download()
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_it_refuses_when_no_pool_was_saved(): void
    {
        // Same refusal as the PDF: an empty sheet headed "Service Charge
        // Distribution" is worse than nothing, because somebody files it.
        $this->staff('AISYAH', 1.5);

        $this->download()->assertNotFound();
    }

    public function test_it_needs_the_service_charge_ability(): void
    {
        $this->pool();

        $this->download($this->user(['hr.attendance']))->assertForbidden();
    }

    public function test_the_figures_land_in_the_right_columns(): void
    {
        $this->pool(3000);
        $this->staff('AISYAH', 1.5);
        $this->staff('BALQIS', 1.5);

        $grid = $this->grid($this->download()->assertOk());

        $header = $this->rowStartingWith($grid, 'No.');
        $this->assertNotNull($header, 'The sheet must carry a header row.');

        $columns = array_map(fn ($c) => trim((string) $c), $header);
        $name    = array_search('Name', $columns, true);
        $points  = array_search('Service Points', $columns, true);
        $net     = array_search('Net (RM)', $columns, true);
        $status  = array_search('Status', $columns, true);

        $this->assertNotFalse($name);
        $this->assertNotFalse($points);
        $this->assertNotFalse($net);

        $row = $this->rowStartingWith($grid, '1');
        $this->assertNotNull($row);

        $this->assertSame('AISYAH', trim((string) $row[$name]));
        $this->assertEqualsWithDelta(1.5, (float) $row[$points], 0.001);
        // RM3000 over 3 points is RM1000 a point; 1.5 points is RM1500.
        $this->assertEqualsWithDelta(1500, (float) $row[$net], 0.01);
        $this->assertSame('Paid', trim((string) $row[$status]));
    }

    /**
     * The column the PDF cannot carry. Three different reasons produce the
     * same zero and they mean different things to whoever reads this.
     */
    public function test_an_excluded_row_says_why_in_words(): void
    {
        $pool    = $this->pool(3000);
        $this->staff('AISYAH', 1.5);
        $dropped = $this->staff('BALQIS', 1.5);

        $pool->update(['excluded_employees' => [$dropped->id]]);

        $grid = $this->grid($this->download()->assertOk());

        $header = $this->rowStartingWith($grid, 'No.');
        $status = array_search('Status', array_map(fn ($c) => trim((string) $c), $header), true);
        $net    = array_search('Net (RM)', array_map(fn ($c) => trim((string) $c), $header), true);

        $row = $this->rowStartingWith($grid, '2');

        $this->assertSame('Excluded from this pool', trim((string) $row[$status]));
        $this->assertEqualsWithDelta(0, (float) $row[$net], 0.01);
    }

    /** The pool's own arithmetic, so the sheet can answer "why this rate". */
    public function test_the_pool_summary_is_on_the_sheet(): void
    {
        $this->pool(3000);
        $this->staff('AISYAH', 1.5);
        $this->staff('BALQIS', 1.5);

        $grid = $this->grid($this->download()->assertOk());
        $flat = array_map(fn ($r) => trim((string) ($r[0] ?? '')), $grid);

        $this->assertContains('Service charge collected (RM)', $flat);
        $this->assertContains('RM per point', $flat);
        $this->assertContains('Undistributed remainder (RM)', $flat);

        $perPoint = $this->rowStartingWith($grid, 'RM per point');
        $this->assertEqualsWithDelta(1000, (float) $perPoint[2], 0.01);
    }

    /**
     * An uncalculated pool is worked out live and moves, so the sheet says so
     * on its own face — a caveat that lives only on the screen the file came
     * from is lost the moment the file is emailed to somebody else.
     */
    public function test_an_uncalculated_pool_is_marked_on_the_sheet_and_in_the_name(): void
    {
        $this->pool();
        $this->staff('AISYAH', 1.5);

        $response = $this->download()->assertOk();

        $this->assertStringContainsString('NOT-CALCULATED',
            $response->headers->get('content-disposition'));

        $flat = array_map(fn ($r) => (string) ($r[0] ?? ''), $this->grid($response));
        $this->assertNotEmpty(array_filter($flat, fn ($c) => str_contains($c, 'NOT CALCULATED')));
    }

    /** And a calculated one carries neither the banner nor the suffix. */
    public function test_a_calculated_pool_carries_no_warning(): void
    {
        $this->pool();
        $this->staff('AISYAH', 1.5);

        app(ServiceChargeDistribution::class)->freeze(
            $this->company->id, [$this->outlet->id],
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'),
            $this->outlet->id, $this->manager->id,
        );

        $response = $this->download()->assertOk();

        $this->assertStringNotContainsString('NOT-CALCULATED',
            $response->headers->get('content-disposition'));

        $flat = array_map(fn ($r) => (string) ($r[0] ?? ''), $this->grid($response));
        $this->assertEmpty(array_filter($flat, fn ($c) => str_contains($c, 'NOT CALCULATED')));
    }
}
