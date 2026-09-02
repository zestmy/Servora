<?php

namespace Tests\Feature;

use App\Livewire\Settings\FormTemplateEdit;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtering the items already inside a form template.
 *
 * A dry-store count sheet runs to a hundred lines, and finding the one whose
 * default quantity is wrong meant scrolling. The filter is the fix, but the
 * list it filters is also the list you drag to set the walking order, and
 * those two things fight: a drop inside a filtered view hands the server only
 * the rows on screen, and renumbering those 0..n lands them on positions still
 * held by the rows the filter is hiding. So the tests that matter here are the
 * ones about what the filter must NOT do — reorder, and lie about position.
 */
class FormTemplateLineFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private UnitOfMeasure $uom;
    private FormTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Filter Co',
            'slug'      => Str::slug('Filter Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $this->company->id,
            'name'       => 'Main',
            'code'       => 'MAIN',
            'is_active'  => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'outlet_id'  => $outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['abbreviation' => 'kg'],
            ['name' => 'Kilogram', 'type' => 'weight']
        );

        $this->template = FormTemplate::create([
            'company_id' => $this->company->id,
            'name'       => 'Dry store count',
            'form_type'  => 'stock_take',
            'is_active'  => true,
        ]);

        $this->actingAs($this->user);
    }

    /** @param array<int, string> $names in the order they should sit in the template */
    private function lines(array $names): array
    {
        $ids = [];

        foreach ($names as $i => $name) {
            $ingredient = Ingredient::create([
                'company_id'    => $this->company->id,
                'name'          => $name,
                'base_uom_id'   => $this->uom->id,
                'recipe_uom_id' => $this->uom->id,
            ]);

            $ids[$name] = FormTemplateLine::create([
                'form_template_id' => $this->template->id,
                'item_type'        => 'ingredient',
                'ingredient_id'    => $ingredient->id,
                'default_quantity' => 0,
                'sort_order'       => $i,
            ])->id;
        }

        return $ids;
    }

    private function screen()
    {
        return Livewire::actingAs($this->user)->test(FormTemplateEdit::class, ['id' => $this->template->id]);
    }

    /** @return array<int, string> */
    private function visibleNames($component): array
    {
        return array_column($component->viewData('visibleLines'), 'item_name');
    }

    public function test_the_filter_narrows_the_items_in_the_template(): void
    {
        $this->lines(['PLAIN FLOUR', 'BREAD FLOUR', 'CASTER SUGAR']);

        $screen = $this->screen()->set('lineFilter', 'flour');

        $this->assertSame(['PLAIN FLOUR', 'BREAD FLOUR'], $this->visibleNames($screen));
    }

    public function test_the_filter_is_case_insensitive_and_matches_mid_word(): void
    {
        $this->lines(['CASTER SUGAR', 'PLAIN FLOUR']);

        $screen = $this->screen()->set('lineFilter', 'ugA');

        $this->assertSame(['CASTER SUGAR'], $this->visibleNames($screen));
    }

    public function test_a_filtered_row_keeps_its_real_position_in_the_template(): void
    {
        $this->lines(['ONE', 'TWO', 'THREE', 'TARGET']);

        $screen = $this->screen()->set('lineFilter', 'target');

        $rows = $screen->viewData('visibleLines');
        $this->assertCount(1, $rows);

        // Fourth in the template, not "1" because it is first in the filtered view.
        $this->assertSame(4, $rows[0]['position']);
    }

    public function test_reordering_is_refused_while_the_list_is_filtered(): void
    {
        $ids = $this->lines(['PLAIN FLOUR', 'BREAD FLOUR', 'CASTER SUGAR']);

        $screen = $this->screen()
            ->set('lineFilter', 'flour')
            ->call('reorderLines', [$ids['BREAD FLOUR'], $ids['PLAIN FLOUR']]);

        // Nothing moved: the sugar the filter was hiding still holds position 2.
        $this->assertSame(0, FormTemplateLine::find($ids['PLAIN FLOUR'])->sort_order);
        $this->assertSame(1, FormTemplateLine::find($ids['BREAD FLOUR'])->sort_order);
        $this->assertSame(2, FormTemplateLine::find($ids['CASTER SUGAR'])->sort_order);

        // And the screen still agrees with the database.
        $this->assertSame(['PLAIN FLOUR', 'BREAD FLOUR'], $this->visibleNames($screen));
    }

    public function test_reordering_still_works_with_no_filter(): void
    {
        $ids = $this->lines(['PLAIN FLOUR', 'BREAD FLOUR', 'CASTER SUGAR']);

        $this->screen()->call('reorderLines', [
            $ids['CASTER SUGAR'], $ids['PLAIN FLOUR'], $ids['BREAD FLOUR'],
        ]);

        $this->assertSame(0, FormTemplateLine::find($ids['CASTER SUGAR'])->sort_order);
        $this->assertSame(1, FormTemplateLine::find($ids['PLAIN FLOUR'])->sort_order);
        $this->assertSame(2, FormTemplateLine::find($ids['BREAD FLOUR'])->sort_order);
    }

    public function test_a_filter_that_matches_nothing_says_so_and_can_be_cleared(): void
    {
        $this->lines(['PLAIN FLOUR']);

        $screen = $this->screen()->set('lineFilter', 'no-such-item');

        $this->assertSame([], $this->visibleNames($screen));
        $screen->assertSee('Nothing in this template matches')
               ->assertDontSee('No items yet');

        $screen->call('clearLineFilter');

        $this->assertSame('', $screen->get('lineFilter'));
        $this->assertSame(['PLAIN FLOUR'], $this->visibleNames($screen));
    }

    public function test_an_empty_template_still_shows_the_no_items_yet_state(): void
    {
        $screen = $this->screen();

        $screen->assertSee('No items yet')
               ->assertDontSee('Nothing in this template matches');
    }

    public function test_adding_an_item_is_unaffected_by_an_active_filter(): void
    {
        $this->lines(['PLAIN FLOUR']);

        $sugar = Ingredient::create([
            'company_id'    => $this->company->id,
            'name'          => 'CASTER SUGAR',
            'base_uom_id'   => $this->uom->id,
            'recipe_uom_id' => $this->uom->id,
        ]);

        $screen = $this->screen()
            ->set('lineFilter', 'flour')
            ->call('addIngredient', $sugar->id);

        // The line is really on the template, even though the filter hides it.
        $this->assertSame(2, FormTemplateLine::where('form_template_id', $this->template->id)->count());
        $this->assertSame(['PLAIN FLOUR'], $this->visibleNames($screen));

        $screen->call('clearLineFilter');
        $this->assertSame(['PLAIN FLOUR', 'CASTER SUGAR'], $this->visibleNames($screen));
    }
}
