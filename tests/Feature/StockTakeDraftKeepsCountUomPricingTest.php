<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StockTakeForm;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A stock take counts in the RECIPE uom, so it must be priced in the recipe uom.
 *
 * Loading a template built the line through buildLine(), which costs via
 * UomService::convertCost() and lands on the price per counted unit. Reopening
 * the saved draft then re-priced every line off ingredient->current_cost, which
 * is the cost per BASE uom — so a dough bought by the 10-piece batch came back
 * at the batch price against a per-piece count: RM2.845 became RM28.45, and the
 * count sheet printed from that draft carried the inflated value.
 *
 * The draft still re-prices (that was the point of the refresh) — it just does
 * it in the unit the line is counted in.
 */
class StockTakeDraftKeepsCountUomPricingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Ingredient $dough;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Dough Co',
            'slug'      => Str::slug('Dough Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id,
            'name'       => 'Main',
            'code'       => 'MAIN',
            'is_active'  => true,
        ]);

        $this->user = User::factory()->create([
            'company_id'           => $this->company->id,
            'outlet_id'            => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.stock_takes.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $batch = UnitOfMeasure::create(['name' => 'Batch', 'abbreviation' => 'batch', 'type' => 'count']);
        $piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pcs',   'type' => 'count']);

        // Bought by the batch at RM28.45, counted by the piece — 1 batch = 10 pcs.
        $this->dough = Ingredient::create([
            'company_id'    => $this->company->id,
            'name'          => 'Pizza Dough',
            'base_uom_id'   => $batch->id,
            'recipe_uom_id' => $piece->id,
            'current_cost'  => 28.45,
            'is_active'     => true,
        ]);

        IngredientUomConversion::create([
            'ingredient_id' => $this->dough->id,
            'from_uom_id'   => $batch->id,
            'to_uom_id'     => $piece->id,
            'factor'        => 10,
        ]);

        $this->actingAs($this->user);
    }

    private function templateWithDough(): FormTemplate
    {
        $template = FormTemplate::create([
            'company_id' => $this->company->id,
            'name'       => 'Weekly Count',
            'form_type'  => 'stock_take',
            'is_active'  => true,
        ]);

        FormTemplateLine::create([
            'form_template_id' => $template->id,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $this->dough->id,
            'sort_order'       => 1,
        ]);

        return $template;
    }

    public function test_a_template_prices_the_line_in_the_uom_it_is_counted_in(): void
    {
        $template = $this->templateWithDough();

        $lines = Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate')
            ->get('lines');

        $this->assertSame(2.845, round(floatval($lines[0]['unit_cost']), 4));
    }

    public function test_saving_a_draft_and_reopening_it_does_not_change_the_price(): void
    {
        $template = $this->templateWithDough();

        $component = Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate')
            ->set('lines.0.actual_quantity', '4');

        $before = round(floatval($component->get('lines')[0]['unit_cost']), 4);

        $component->call('save', 'save');
        $draftId = $component->get('recordId');

        $this->assertNotNull($draftId);
        $this->assertSame('draft', StockTake::find($draftId)->status);

        $reopened = Livewire::actingAs($this->user)->test(StockTakeForm::class, ['id' => $draftId]);
        $after    = round(floatval($reopened->get('lines')[0]['unit_cost']), 4);

        $this->assertSame(
            $before,
            $after,
            'Reopening a draft re-priced the line off the base-uom cost — RM2.845 came back as RM28.45.'
        );
        $this->assertSame(2.845, $after);
    }

    public function test_a_reopened_draft_still_picks_up_a_new_ingredient_cost(): void
    {
        $template = $this->templateWithDough();

        $component = Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate')
            ->set('lines.0.actual_quantity', '4')
            ->call('save', 'save');

        // Price rises after the draft was saved: 1 batch now RM34.00 => RM3.40 a piece.
        $this->dough->update(['current_cost' => 34.00]);

        $reopened = Livewire::actingAs($this->user)->test(StockTakeForm::class, ['id' => $component->get('recordId')]);

        $this->assertSame(3.4, round(floatval($reopened->get('lines')[0]['unit_cost']), 4));
    }

    public function test_a_completed_stock_take_keeps_the_price_it_was_counted_at(): void
    {
        $template = $this->templateWithDough();

        $component = Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate')
            ->set('lines.0.actual_quantity', '4')
            ->call('save', 'complete');

        $stockTake = StockTake::where('company_id', $this->company->id)->latest('id')->first();
        $this->assertSame('completed', $stockTake->status);

        $this->dough->update(['current_cost' => 99.00]);

        $reopened = Livewire::actingAs($this->user)->test(StockTakeForm::class, ['id' => $stockTake->id]);

        $this->assertSame(2.845, round(floatval($reopened->get('lines')[0]['unit_cost']), 4));
    }
}
