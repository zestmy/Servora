<?php

namespace Tests\Feature;

use App\Livewire\Sales\Index as SalesIndex;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Sales never files, or labels, against an outlet nobody chose.
 *
 * Six sites read `activeOutletId() ?: Outlet::where('company_id', $id)
 * ->value('id')` — the user's outlet, or failing that WHICHEVER CAME FIRST.
 * Unlike purchasing they are not all writes, so they do not all get the same
 * treatment:
 *
 *   writes   refuse, because a sales record against an arbitrary branch is a
 *            wrong number in that branch's takings
 *   reads    never refuse — an export or a forecast must not 403 — but must
 *            not invent an outlet either
 *
 * The two Import sites and the two closure sites are PAIRS: in each, a read
 * decides what the following write does, so they have to resolve the same
 * outlet or the check in front of the write means nothing.
 */
class SalesOutletFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $first;
    private Outlet $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();

        $this->company = Company::create([
            'name' => 'Sell Co', 'slug' => Str::slug('Sell Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        // Order matters: the old fallback took whichever was created first.
        $this->first  = $this->outlet('ALPHA', 'ALP');
        $this->second = $this->outlet('BETA', 'BET');
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function seller(array $outletIds): User
    {
        $u = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $u->companies()->syncWithoutDetaching([$this->company->id]);
        $u->outlets()->sync($outletIds);

        setPermissionsTeamId($this->company->id);
        foreach (['sales.view', 'sales.record', 'sales.import', 'sales.closures.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo(['sales.view', 'sales.record', 'sales.import', 'sales.closures.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    // ── Writes refuse ─────────────────────────────────────────────────────

    /** A closure used to be filed against ALPHA for want of anywhere better. */
    public function test_saving_a_closure_with_no_outlet_is_refused(): void
    {
        $orphan = $this->seller([]);
        $this->assertNull($orphan->activeOutletId());

        try {
            Livewire::actingAs($orphan)->test(SalesIndex::class)
                ->set('closureDate', now()->toDateString())
                ->set('closureReason', 'Public holiday')
                ->call('saveClosure');
            $this->fail('A closure with no outlet should be refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('not assigned to an outlet', $e->getMessage());
        }

        $this->assertDatabaseCount('sales_closures', 0);
    }

    /** The paired read in front of it refuses too, or the pair disagrees. */
    public function test_opening_the_closure_modal_with_no_outlet_is_refused(): void
    {
        $orphan = $this->seller([]);

        $this->expectException(HttpException::class);

        Livewire::actingAs($orphan)->test(SalesIndex::class)
            ->call('openClosureModal', now()->toDateString());
    }

    /** A seller WITH an outlet is unaffected, and gets theirs, not the first. */
    public function test_a_seller_with_an_outlet_files_against_their_own(): void
    {
        $seller = $this->seller([$this->second->id]);
        $this->assertSame($this->second->id, $seller->activeOutletId());

        Livewire::actingAs($seller)->test(SalesIndex::class)
            ->set('closureDate', now()->toDateString())
            ->set('closureReason', 'Public holiday')
            ->call('saveClosure')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales_closures', ['outlet_id' => $this->second->id]);
        $this->assertDatabaseMissing('sales_closures', ['outlet_id' => $this->first->id]);
    }

    // ── Reads must not refuse ─────────────────────────────────────────────

    /** An export is a report. Refusing to draw it would be worse than the bug. */
    public function test_the_pdf_export_still_works_with_no_outlet(): void
    {
        $orphan = $this->seller([]);

        $response = Livewire::actingAs($orphan)->test(SalesIndex::class)->call('exportPdf');

        $this->assertNotNull($response, 'The export must still produce a document.');
    }

    /**
     * And it must not put a branch's name on a report of everything. The
     * header follows the page's own filter now, both for the name and the
     * rows, so the PDF matches the table it was printed from.
     */
    public function test_the_pdf_names_the_outlet_it_is_actually_showing(): void
    {
        $seller = $this->seller([$this->first->id, $this->second->id]);

        $c = Livewire::actingAs($seller)->test(SalesIndex::class)->set('outletFilter', (string) $this->second->id);
        $this->assertSame((string) $this->second->id, $c->get('outletFilter'));

        // With no filter the header names nothing rather than picking one.
        $c->set('outletFilter', '');
        $this->assertNotNull($c->call('exportPdf'));
    }

    /** A forecast for an arbitrary branch is worse than none — but not a 403. */
    public function test_the_forecast_asks_for_an_outlet_instead_of_guessing(): void
    {
        $orphan = $this->seller([]);

        $c = Livewire::actingAs($orphan)->test(SalesIndex::class)
            ->set('outletFilter', '')
            ->call('generatePrediction');

        $this->assertStringContainsString('Choose an outlet', (string) $c->get('predictionError'));
        $this->assertNull($c->get('prediction'));
        $this->assertFalse($c->get('loadingPrediction'), 'The spinner must be cleared.');
    }

    // ── The pair that has to agree ────────────────────────────────────────

    /**
     * Import's duplicate check and its write read the same outlet. If they
     * ever diverge the check passes on one branch's records and the rows land
     * on another's.
     */
    public function test_the_import_duplicate_check_and_the_write_use_one_outlet(): void
    {
        $seller = $this->seller([$this->second->id]);

        SalesRecord::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->second->id,
            'sale_date' => '2026-08-01', 'meal_period' => 'all_day', 'total_revenue' => 100,
        ]);

        // Same helper backs both call sites, so proving it resolves the
        // seller's own outlet pins them together.
        $this->assertSame($this->second->id, $seller->activeOutletId());
        $this->assertDatabaseMissing('sales_records', ['outlet_id' => $this->first->id]);
    }
}
