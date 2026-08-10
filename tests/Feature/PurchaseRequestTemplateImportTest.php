<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\PurchaseRequestForm;
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

    private function template(string $name, array $items): FormTemplate
    {
        $t = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => $name,
            'form_type' => 'stock_take', 'is_active' => true,
        ]);

        foreach ($items as $i => [$type, $id, $qty]) {
            FormTemplateLine::create([
                'form_template_id' => $t->id,
                'item_type'        => $type,
                'ingredient_id'    => $type === 'ingredient' ? $id : null,
                'recipe_id'        => $type === 'recipe' ? $id : null,
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
}
