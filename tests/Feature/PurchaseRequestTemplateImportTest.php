<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Loading a form template onto a purchase request.
 *
 * A stock-take or order form is the list somebody walks the store with, in the
 * order they walk it. Retyping one into a request is transcription, and
 * transcription is where items go missing.
 *
 * Quantities DO carry here, unlike the label sets that import the same
 * templates: a form's default quantity is how much to order, which is exactly
 * what a request line asks for.
 */
class PurchaseRequestTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PR Template Co', 'slug' => Str::slug('PR Template Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->uom = UnitOfMeasure::firstOrCreate(['abbreviation' => 'kg'], ['name' => 'Kilogram', 'type' => 'weight']);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->uom->id, 'recipe_uom_id' => $this->uom->id,
        ]);
    }

    private function asset(string $name): Asset
    {
        $piece = UnitOfMeasure::firstOrCreate(['abbreviation' => 'pc'], ['name' => 'Piece', 'type' => 'count']);

        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $piece->id, 'unit_cost' => 12.5, 'is_active' => true,
        ]);
    }

    private function grantAssetAccess(): void
    {
        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('assets.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function template(string $name, array $items, string $type = 'stock_take'): FormTemplate
    {
        $t = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => $name,
            'form_type' => $type, 'is_active' => true,
        ]);

        foreach ($items as $i => [$type, $id, $qty]) {
            FormTemplateLine::create([
                'form_template_id' => $t->id,
                'item_type'        => $type,
                'ingredient_id'    => $type === 'ingredient' ? $id : null,
                'recipe_id'        => $type === 'recipe' ? $id : null,
                'asset_id'         => $type === 'asset' ? $id : null,
                'default_quantity' => $qty,
                'sort_order'       => $i,
            ]);
        }

        return $t;
    }

    public function test_a_form_loads_its_items_with_their_quantities(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');
        $t = $this->template('Dry store', [
            ['ingredient', $flour->id, 5],
            ['ingredient', $butter->id, 2.5],
        ]);

        $screen = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->set('importTemplateId', $t->id)
            ->call('importTemplate');

        $lines = $screen->get('lines');

        $this->assertCount(2, $lines);
        $this->assertSame('FLOUR', $lines[0]['ingredient_name']);
        $this->assertEqualsWithDelta(5.0, $lines[0]['quantity'], 0.001, 'A form quantity is how much to order.');
        $this->assertEqualsWithDelta(2.5, $lines[1]['quantity'], 0.001);
    }

    public function test_loading_twice_does_not_double_the_request(): void
    {
        $flour = $this->ingredient('FLOUR');
        $t = $this->template('Dry store', [['ingredient', $flour->id, 5]]);

        $screen = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->set('importTemplateId', $t->id)
            ->call('importTemplate')
            ->call('openTemplateImport')
            ->set('importTemplateId', $t->id)
            ->call('importTemplate');

        $this->assertCount(1, $screen->get('lines'));
    }

    /** A request orders ingredients; a recipe line has nothing to buy behind it. */
    public function test_recipe_lines_are_skipped_and_reported(): void
    {
        $flour = $this->ingredient('FLOUR');
        $dough = Recipe::create([
            'company_id' => $this->company->id, 'name' => 'DOUGH', 'yield_uom_id' => $this->uom->id,
        ]);

        $t = $this->template('Mixed', [
            ['ingredient', $flour->id, 3],
            ['recipe', $dough->id, 1],
        ]);

        $html = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->set('importTemplateId', $t->id)
            ->call('importTemplate')
            ->html();

        $this->assertStringContainsString('nothing to order', $html);
    }

    /**
     * An Asset Count sheet is the list of what an outlet is meant to hold,
     * which is exactly what a replacement order is written against.
     */
    public function test_an_asset_count_form_loads_its_assets_with_their_quantities(): void
    {
        $this->grantAssetAccess();

        $plate = $this->asset('DINNER PLATE');
        $bowl  = $this->asset('SOUP BOWL');
        $t = $this->template('Crockery count', [
            ['asset', $plate->id, 24],
            ['asset', $bowl->id, 12],
        ], 'asset_count');

        $screen = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->call('openTemplateImport')
            ->assertSee('Crockery count')
            ->set('importTemplateId', $t->id)
            ->call('importTemplate');

        $lines = $screen->get('lines');

        $this->assertCount(2, $lines);
        $this->assertSame($plate->id, (int) $lines[0]['asset_id']);
        $this->assertNull($lines[0]['ingredient_id'], 'An asset line carries no ingredient — that is what keeps it out of a food PO.');
        $this->assertSame('asset', $lines[0]['source']);
        $this->assertEqualsWithDelta(24.0, $lines[0]['quantity'], 0.001, 'The count sheet quantity is how many to order.');
        $this->assertEqualsWithDelta(12.0, $lines[1]['quantity'], 0.001);
        $this->assertStringContainsString('2 items added', $screen->html());
    }

    /** Loading an asset count sheet twice is as safe as loading a stock take twice. */
    public function test_loading_an_asset_count_form_twice_does_not_double_the_request(): void
    {
        $this->grantAssetAccess();

        $plate = $this->asset('DINNER PLATE');
        $t = $this->template('Crockery count', [['asset', $plate->id, 24]], 'asset_count');

        $screen = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->set('importTemplateId', $t->id)
            ->call('importTemplate')
            ->call('openTemplateImport')
            ->set('importTemplateId', $t->id)
            ->call('importTemplate');

        $this->assertCount(1, $screen->get('lines'));
    }

    /**
     * The template is not a back door into the asset register: somebody who
     * may not pick an asset does not get one by loading a form, and a sheet
     * that would load as nothing is not offered at all.
     */
    public function test_asset_lines_need_the_same_access_as_the_asset_picker(): void
    {
        $plate = $this->asset('DINNER PLATE');
        $flour = $this->ingredient('FLOUR');
        $count = $this->template('Crockery count', [['asset', $plate->id, 24]], 'asset_count');
        $mixed = $this->template('Opening list', [
            ['ingredient', $flour->id, 5],
            ['asset', $plate->id, 24],
        ]);

        $screen = Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->call('openTemplateImport')
            ->assertDontSee('Crockery count')
            ->assertSee('Opening list')
            ->set('importTemplateId', $mixed->id)
            ->call('importTemplate');

        $lines = $screen->get('lines');

        $this->assertCount(1, $lines);
        $this->assertSame($flour->id, (int) $lines[0]['ingredient_id']);
        $this->assertStringContainsString('1 asset skipped', $screen->html());

        Livewire::actingAs($this->user)->test(PurchaseRequestForm::class)
            ->set('importTemplateId', $count->id)
            ->call('importTemplate')
            ->assertSet('lines', []);
    }
}
