<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StaffMealForm;
use App\Livewire\Inventory\StockTakeForm;
use App\Livewire\Inventory\TransferForm;
use App\Livewire\Inventory\WastageForm;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\StaffMealRecord;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WastageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Unit cost is shown on the stock forms, never entered.
 *
 * Prices come from purchasing — invoices, GRNs, supplier price lists, the
 * market list. Letting a stock take or a wastage note carry its own typed
 * price makes the same item worth two different things depending on which
 * screen recorded it, and moves stock value with no document behind it.
 *
 * Taking the input out of the markup is the visible half and the weaker half:
 * `lines` is a public Livewire property, so the browser can still put any
 * number on the wire. These tests drive that attack directly — set the cost to
 * 999 the way a crafted request would, save, and assert the stored figure is
 * the one the server derived.
 */
class StockFormsLockUnitCostTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $other;
    private User $user;
    private Ingredient $dough;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Lock Co', 'slug' => Str::slug('Lock Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id, $this->other->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(array_map(
            fn ($p) => Permission::findOrCreate($p, 'web'),
            ['inventory.view', 'inventory.stock_takes.record', 'inventory.wastage.record',
             'inventory.transfers.record', 'inventory.staff_meals.record']
        ));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $batch = UnitOfMeasure::create(['name' => 'Batch', 'abbreviation' => 'batch', 'type' => 'count']);
        $piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pcs', 'type' => 'count']);

        // RM28.45 a batch, 1 batch = 10 pieces => RM2.845 a piece.
        $this->dough = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Pizza Dough',
            'base_uom_id' => $batch->id, 'recipe_uom_id' => $piece->id,
            'current_cost' => 28.45, 'is_active' => true,
        ]);
        IngredientUomConversion::create([
            'ingredient_id' => $this->dough->id,
            'from_uom_id' => $batch->id, 'to_uom_id' => $piece->id, 'factor' => 10,
        ]);

        $this->actingAs($this->user);
    }

    // ── The cost map itself refuses the browser ──────────────────────────

    public function test_the_cost_map_cannot_be_written_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lineCosts', ['ingredient:' . $this->dough->id . ':uom:' . $this->dough->recipe_uom_id => 999]);
    }

    // ── Each form ignores a cost pushed over the wire ────────────────────

    public function test_a_stock_take_stores_the_derived_cost_not_the_submitted_one(): void
    {
        Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.actual_quantity', '4')
            ->set('lines.0.unit_cost', '999')      // what a crafted request sends
            ->call('save', 'save');

        $line = StockTake::latest('id')->first()->lines()->first();

        $this->assertSame(2.845, round((float) $line->unit_cost, 4));
    }

    public function test_a_stock_take_total_ignores_the_submitted_cost(): void
    {
        Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.actual_quantity', '4')
            ->set('lines.0.unit_cost', '999')
            ->call('save', 'save');

        $this->assertSame(11.38, round((float) StockTake::latest('id')->first()->total_stock_cost, 2));
    }

    public function test_a_wastage_note_stores_the_derived_cost(): void
    {
        Livewire::actingAs($this->user)->test(WastageForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.quantity', '4')
            ->set('lines.0.unit_cost', '999')
            ->call('save');

        $line = WastageRecord::latest('id')->first()->lines()->first();

        $this->assertSame(2.845, round((float) $line->unit_cost, 4));
        $this->assertSame(11.38, round((float) $line->total_cost, 2));
    }

    public function test_a_staff_meal_stores_the_derived_cost(): void
    {
        Livewire::actingAs($this->user)->test(StaffMealForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.quantity', '2')
            ->set('lines.0.unit_cost', '999')
            ->call('save');

        $line = StaffMealRecord::latest('id')->first()->lines()->first();

        // Staff meals record in the base UOM, so the batch price is right here.
        $this->assertSame(28.45, round((float) $line->unit_cost, 4));
    }

    public function test_a_transfer_stores_the_derived_cost(): void
    {
        Livewire::actingAs($this->user)->test(TransferForm::class)
            ->set('from_outlet_id', (string) $this->outlet->id)
            ->set('to_outlet_id', (string) $this->other->id)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.quantity', '3')
            ->set('lines.0.unit_cost', '999')
            ->call('save');

        $line = OutletTransfer::latest('id')->first()->lines()->first();

        $this->assertSame(28.45, round((float) $line->unit_cost, 4));
    }

    public function test_shifting_the_uom_cannot_smuggle_a_price_past_the_map(): void
    {
        // uom_id is part of the map key and is itself a public array member, so
        // a crafted request could change it to miss the map. The miss must fall
        // through to a fresh look-up, not to the number the request supplied.
        $spanner = UnitOfMeasure::create(['name' => 'Spanner', 'abbreviation' => 'sp', 'type' => 'count']);

        Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->call('addIngredient', $this->dough->id)
            ->set('lines.0.actual_quantity', '4')
            ->set('lines.0.uom_id', $spanner->id)
            ->set('lines.0.unit_cost', '999')
            ->call('save', 'save');

        $stored = (float) StockTake::latest('id')->first()->lines()->first()->unit_cost;

        $this->assertNotSame(999.0, $stored, 'A submitted price survived by shifting the UOM key.');
        $this->assertSame(28.45, round($stored, 4), 'An unrelated UOM prices off the base cost, not the request.');
    }

    // ── Data entry must not be interrupted mid-count ─────────────────────

    public function test_no_stock_form_leaves_a_hidden_control_in_the_tab_order(): void
    {
        foreach ([StockTakeForm::class, WastageForm::class, StaffMealForm::class, TransferForm::class] as $screen) {
            $html = Livewire::actingAs($this->user)->test($screen)
                ->call('addIngredient', $this->dough->id)
                ->html();

            // The row remove button only appears on hover. Focusable and invisible
            // is how a tabbed quantity ended up on the line below — and how a
            // keyboard user lands on an unseen control that deletes a line.
            preg_match_all('/<button[^>]*wire:click="removeLine\([^"]*\)"[^>]*>/i', $html, $m);

            $this->assertNotEmpty($m[0], $screen . ' should still offer a per-row remove.');

            foreach ($m[0] as $button) {
                $this->assertStringContainsString('tabindex="-1"', $button, $screen . ': hidden tab stop.');
                $this->assertStringContainsString('aria-label=', $button, $screen . ': icon button with no name.');
            }
        }
    }

    public function test_every_stock_form_commits_a_line_on_blur_rather_than_on_a_timer(): void
    {
        foreach ([StockTakeForm::class, WastageForm::class, StaffMealForm::class, TransferForm::class] as $screen) {
            $html = Livewire::actingAs($this->user)->test($screen)
                ->call('addIngredient', $this->dough->id)
                ->html();

            // A debounce timer holds the value client-side while the table
            // re-renders around it, and fast entry loses rows outright.
            $this->assertStringNotContainsString(
                'debounce.400ms="lines.',
                $html,
                $screen . ' still holds line input behind a debounce timer.'
            );
            $this->assertStringContainsString('wire:model.blur="lines.', $html, $screen);
        }
    }

    // ── And the markup no longer offers the field ────────────────────────

    public function test_no_stock_form_renders_an_editable_cost_input(): void
    {
        foreach ([StockTakeForm::class, WastageForm::class, StaffMealForm::class, TransferForm::class] as $screen) {
            $html = Livewire::actingAs($this->user)->test($screen)
                ->call('addIngredient', $this->dough->id)
                ->html();

            $this->assertStringNotContainsString(
                '.unit_cost"',
                $html,
                $screen . ' still binds an input to unit_cost; the price belongs to purchasing.'
            );
        }
    }
}
