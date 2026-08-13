<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Purchasing\CreditNoteForm;
use App\Livewire\Purchasing\OrderForm;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Two things that quietly substituted a guess for the truth.
 *
 * OUTLET: four purchasing screens read `activeOutletId() ?? Outlet::where(
 * 'company_id', $id)->value('id')` — the user's outlet, or failing that
 * whichever outlet came first. PicksRecordOutlet exists precisely to replace
 * that default, which "silently filed every multi-outlet record under the
 * wrong location". A purchase order is money; an arbitrary branch is a wrong
 * number in someone's cost report with nothing on screen to say so.
 *
 * NAME: the dashboard greeted people by the first word of their name. Names
 * here are not given-name-first — "MOHD AFFANDY BIN ZULKARNAIN" became "MOHD",
 * which is an honorific, not what anyone is called.
 */
class OutletFallbackAndGreetingTest extends TestCase
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
            'name' => 'Buy Co', 'slug' => Str::slug('Buy Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        // Creation order matters: the old fallback picked whichever came first.
        $this->first  = $this->outlet('ALPHA', 'ALP');
        $this->second = $this->outlet('BETA', 'BET');

        $this->uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_factor' => 1,
        ]);

        $this->ingredient = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Onion', 'code' => 'ONI',
            'base_uom_id' => $this->uom->id, 'recipe_uom_id' => $this->uom->id,
            'purchase_price' => 5, 'pack_size' => 1,
            'yield_percent' => 100, 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Acme', 'code' => 'ACM', 'is_active' => true,
        ]);
    }

    private UnitOfMeasure $uom;
    private Ingredient $ingredient;
    private Supplier $supplier;

    /** One valid line, so validation passes and the outlet code is reached. */
    private function orderLine(): array
    {
        return [[
            'ingredient_id' => $this->ingredient->id, 'ingredient_name' => 'Onion',
            'quantity' => '2', 'uom_id' => $this->uom->id,
            'unit_cost' => '5', 'total_cost' => 10, 'tax_rate_pct' => 0, 'tax_amount' => 0,
        ]];
    }

    private function creditLine(): array
    {
        return [[
            'ingredient_id' => $this->ingredient->id, 'ingredient_name' => 'Onion',
            'quantity' => '1', 'uom_id' => $this->uom->id,
            'unit_price' => '5', 'reason_code' => 'damaged', 'description' => '',
        ]];
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function buyer(array $outletIds): User
    {
        $u = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $u->companies()->syncWithoutDetaching([$this->company->id]);
        $u->outlets()->sync($outletIds);

        setPermissionsTeamId($this->company->id);
        foreach (['purchasing.view', 'purchasing.orders.create', 'purchasing.credit_notes.manage'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->givePermissionTo(['purchasing.view', 'purchasing.orders.create', 'purchasing.credit_notes.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    // ── The fallback ──────────────────────────────────────────────────────

    /**
     * The reported bug. A buyer attached to no outlet used to have their order
     * filed against ALPHA purely because it was created first.
     */
    public function test_an_order_is_refused_rather_than_filed_against_an_arbitrary_outlet(): void
    {
        $orphan = $this->buyer([]);
        $this->assertNull($orphan->activeOutletId(), 'Fixture must have no outlet.');

        try {
            Livewire::actingAs($orphan)->test(OrderForm::class)
                ->set('order_date', now()->toDateString())
                ->set('supplier_id', $this->supplier->id)
                ->set('lines', $this->orderLine())
                ->call('save');
            $this->fail('An order with no outlet to file against should be refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('not assigned to an outlet', $e->getMessage());
        }

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseMissing('purchase_orders', ['outlet_id' => $this->first->id]);
    }

    public function test_a_credit_note_is_refused_the_same_way(): void
    {
        $orphan = $this->buyer([]);

        try {
            Livewire::actingAs($orphan)->test(CreditNoteForm::class)
                ->set('supplier_id', $this->supplier->id)
                ->set('lines', $this->creditLine())
                ->call('save');
            $this->fail('A credit note with no outlet should be refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseCount('credit_notes', 0);
    }

    /** A buyer WITH an outlet is unaffected, and gets their own, not the first. */
    public function test_a_buyer_with_an_outlet_files_against_their_own(): void
    {
        $buyer = $this->buyer([$this->second->id]);

        $this->assertSame($this->second->id, $buyer->activeOutletId(),
            'Their outlet is BETA, which is deliberately not the first one created.');

        Livewire::actingAs($buyer)->test(CreditNoteForm::class)
            ->assertSet('outlet_id', $this->second->id);
    }

    // ── The greeting ──────────────────────────────────────────────────────

    /** The reported bug: the first word of a name is not the person's name. */
    public function test_the_dashboard_greets_by_full_name(): void
    {
        $user = $this->buyer([$this->second->id]);
        $user->update(['name' => 'MOHD AFFANDY BIN ZULKARNAIN']);

        $greeting = Livewire::actingAs($user->fresh())->test(Dashboard::class)->viewData('greeting');

        $this->assertStringContainsString('MOHD AFFANDY BIN ZULKARNAIN', $greeting);
        $this->assertMatchesRegularExpression('/^Good (morning|afternoon|evening), /', $greeting);
    }

    /** A one-word name still reads correctly, and no trailing comma appears. */
    public function test_a_single_word_name_is_greeted_normally(): void
    {
        $user = $this->buyer([$this->second->id]);
        $user->update(['name' => 'CHER']);

        $this->assertStringEndsWith(', CHER',
            Livewire::actingAs($user->fresh())->test(Dashboard::class)->viewData('greeting'));
    }

    /** A blank name must not leave a dangling comma. */
    public function test_a_blank_name_drops_the_comma(): void
    {
        $user = $this->buyer([$this->second->id]);
        $user->update(['name' => '   ']);

        $this->assertMatchesRegularExpression('/^Good (morning|afternoon|evening)$/',
            Livewire::actingAs($user->fresh())->test(Dashboard::class)->viewData('greeting'));
    }
}
