<?php

namespace Tests\Feature;

use App\Livewire\Labels\Sets;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\LabelSet;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Importing a Form Template as a print set.
 *
 * A stock-take or order form is already a walk down the shelf, written out in
 * the order someone moves through it — which is exactly what a print set's order
 * means. Retyping one into a set is transcription, and transcription is where
 * items go missing.
 *
 * The three things worth pinning are the ones a careless implementation gets
 * wrong: order survives the copy, a second import adds only what is new rather
 * than doubling the set, and a line pointing at a deleted item is reported
 * instead of silently dropped or crashing the import.
 */
class LabelSetImportFromFormTemplateTest extends TestCase
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
            'name'      => 'Label Import Co',
            'slug'      => Str::slug('Label Import Co') . '-' . uniqid(),
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
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['abbreviation' => 'kg'],
            ['name' => 'Kilogram', 'type' => 'weight']
        );

        $this->actingAs($this->user);
    }

    private function ingredient(string $name): Ingredient
    {
        return Ingredient::create([
            'company_id'    => $this->company->id,
            'name'          => $name,
            'base_uom_id'   => $this->uom->id,
            'recipe_uom_id' => $this->uom->id,
        ]);
    }

    private function recipe(string $name): Recipe
    {
        return Recipe::create([
            'company_id'   => $this->company->id,
            'name'         => $name,
            'yield_uom_id' => $this->uom->id,
        ]);
    }

    /** @param array<int, array{0: string, 1: int}> $items [item_type, id] in order */
    private function template(string $name, array $items): FormTemplate
    {
        $template = FormTemplate::create([
            'company_id' => $this->company->id,
            'name'       => $name,
            'form_type'  => 'stock_take',
            'is_active'  => true,
        ]);

        foreach ($items as $i => [$type, $id]) {
            FormTemplateLine::create([
                'form_template_id' => $template->id,
                'item_type'        => $type,
                'ingredient_id'    => $type === 'ingredient' ? $id : null,
                'recipe_id'        => $type === 'recipe' ? $id : null,
                'default_quantity' => 7,
                'sort_order'       => $i,
            ]);
        }

        return $template;
    }

    private function screen()
    {
        return Livewire::actingAs($this->user)->test(Sets::class)->set('outletId', $this->outlet->id);
    }

    public function test_a_form_template_becomes_a_print_set_in_the_same_order(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');
        $dough  = $this->recipe('DOUGH');

        $template = $this->template('Dry store count', [
            ['ingredient', $flour->id],
            ['recipe', $dough->id],
            ['ingredient', $butter->id],
        ]);

        $this->screen()
            ->call('openImport')
            ->set('importTemplateId', $template->id)
            ->set('importName', 'Dry store')
            ->call('importTemplate')
            ->assertHasNoErrors();

        $set = LabelSet::where('name', 'Dry store')->firstOrFail();

        $this->assertSame($this->outlet->id, $set->outlet_id, 'Sets are outlet-owned.');

        $names = $set->lines()->orderBy('sort_order')->get()
            ->map(fn ($l) => $l->labelable?->name)
            ->all();

        $this->assertSame(
            ['FLOUR', 'DOUGH', 'BUTTER'],
            $names,
            'A form is already sequenced the way the shelf is walked; that sequence is the point.'
        );
    }

    /**
     * The template's quantity is how much to order or count. A print set's is
     * how much is in the container being labelled, and the template carries no
     * unit to disambiguate — importing the number would print a confident wrong
     * figure onto food.
     */
    public function test_quantities_are_not_carried_across(): void
    {
        $flour = $this->ingredient('FLOUR');
        $template = $this->template('Dry store count', [['ingredient', $flour->id]]);

        $this->screen()
            ->call('openImport')
            ->set('importTemplateId', $template->id)
            ->set('importName', 'Dry store')
            ->call('importTemplate');

        $line = LabelSet::where('name', 'Dry store')->firstOrFail()->lines()->firstOrFail();

        $this->assertNull($line->quantity);
    }

    public function test_importing_the_same_template_twice_adds_only_what_is_new(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');

        $template = $this->template('Dry store count', [['ingredient', $flour->id]]);

        $screen = $this->screen()
            ->call('openImport')
            ->set('importTemplateId', $template->id)
            ->set('importName', 'Dry store')
            ->call('importTemplate');

        $set = LabelSet::where('name', 'Dry store')->firstOrFail();
        $this->assertSame(1, $set->lines()->count());

        // The form grows, as forms do.
        FormTemplateLine::create([
            'form_template_id' => $template->id,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $butter->id,
            'default_quantity' => 1,
            'sort_order'       => 1,
        ]);

        $screen->call('openImport')
            ->set('importTemplateId', $template->id)
            ->set('importTarget', 'current')
            ->call('importTemplate');

        $this->assertSame(
            2,
            $set->lines()->count(),
            'Re-importing must top the set up, not double it.'
        );
    }

    public function test_a_line_whose_item_was_deleted_is_skipped_and_reported(): void
    {
        $flour = $this->ingredient('FLOUR');
        $gone  = $this->ingredient('DISCONTINUED');

        $template = $this->template('Dry store count', [
            ['ingredient', $flour->id],
            ['ingredient', $gone->id],
        ]);

        $gone->delete();

        $html = $this->screen()
            ->call('openImport')
            ->set('importTemplateId', $template->id)
            ->set('importName', 'Dry store')
            ->call('importTemplate')
            ->assertHasNoErrors()
            ->html();

        $set = LabelSet::where('name', 'Dry store')->firstOrFail();

        $this->assertSame(1, $set->lines()->count());

        // Asserted on what the screen SAYS, not on the session: a skipped line
        // the person is never told about is the same as one silently dropped.
        $this->assertStringContainsString('no longer exists', $html);
    }

    public function test_a_template_must_be_chosen(): void
    {
        $this->screen()
            ->call('openImport')
            ->set('importName', 'Dry store')
            ->call('importTemplate')
            ->assertHasErrors('importTemplateId');

        $this->assertSame(0, LabelSet::count());
    }
}
