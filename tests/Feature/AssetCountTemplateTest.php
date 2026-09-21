<?php

namespace Tests\Feature;

use App\Livewire\Assets\CountForm;
use App\Livewire\Settings\FormTemplateEdit;
use App\Livewire\Settings\FormTemplates;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Form templates for asset counts.
 *
 * Counting the same crockery and equipment every month off a blank sheet is
 * the job templates already do for stock takes; an asset count sheet counts
 * assets, so its template lines point at assets rather than ingredients.
 */
class AssetCountTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private AssetCategory $crockery;
    private Asset $plate;
    private Asset $bowl;
    private Asset $chair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Asset Co', 'slug' => Str::slug('Asset Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        $this->actingAs($this->user);

        $uom = UnitOfMeasure::create([
            'company_id' => $this->company->id, 'name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count', 'is_active' => true,
        ]);
        $this->crockery = AssetCategory::create([
            'company_id' => $this->company->id, 'name' => 'Crockery', 'is_active' => true,
        ]);
        $furniture = AssetCategory::create([
            'company_id' => $this->company->id, 'name' => 'Furniture', 'is_active' => true,
        ]);

        $asset = fn (string $name, AssetCategory $cat) => Asset::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => Str::slug($name),
            'asset_category_id' => $cat->id, 'uom_id' => $uom->id, 'unit_cost' => 12.50, 'is_active' => true,
        ]);

        $this->plate = $asset('Dinner Plate', $this->crockery);
        $this->bowl  = $asset('Soup Bowl', $this->crockery);
        $this->chair = $asset('Dining Chair', $furniture);
    }

    private function template(): FormTemplate
    {
        return FormTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Monthly crockery count',
            'form_type' => 'asset_count', 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    public function test_asset_count_is_an_offered_template_type(): void
    {
        $this->assertArrayHasKey('asset_count', FormTemplate::formTypeOptions());

        Livewire::test(FormTemplates::class)
            ->call('openCreate')
            ->set('name', 'Monthly crockery count')
            ->set('form_type', 'asset_count')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('asset_count', FormTemplate::latest('id')->first()->form_type);
    }

    public function test_the_builder_adds_assets_one_at_a_time_or_a_whole_category(): void
    {
        $template = $this->template();

        Livewire::test(FormTemplateEdit::class, ['id' => $template->id])
            ->set('itemSearch', 'DINNER PL')
            ->assertSee('DINNER PLATE')
            ->assertDontSee('DINING CHAIR')   // a different asset, not matching the search
            ->call('addAsset', $this->plate->id)
            ->call('addAsset', $this->plate->id)     // already on the template
            ->call('loadByAssetCategory', $this->crockery->id);

        $lines = $template->lines()->get();
        $this->assertSame(['DINNER PLATE', 'SOUP BOWL'], $lines->map->itemName()->all(), 'Added once each, in order.');
        $this->assertSame(['asset', 'asset'], $lines->pluck('item_type')->all());
        $this->assertEqualsCanonicalizing([$this->plate->id, $this->bowl->id], $lines->pluck('asset_id')->all());
        $this->assertNull($lines->first()->ingredient_id);
    }

    public function test_a_count_loads_the_template_and_skips_what_is_already_on_it(): void
    {
        $template = $this->template();
        foreach ([$this->plate, $this->bowl] as $i => $asset) {
            FormTemplateLine::create([
                'form_template_id' => $template->id, 'item_type' => 'asset',
                'asset_id' => $asset->id, 'default_quantity' => 0, 'sort_order' => $i,
            ]);
        }

        $count = Livewire::test(CountForm::class)
            ->assertSee('Monthly crockery count')
            ->call('addAsset', $this->plate->id)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate');

        $names = collect($count->get('lines'))->pluck('asset_name')->all();
        $this->assertSame(['DINNER PLATE', 'SOUP BOWL'], $names, 'The plate was already on the sheet; only the bowl is added.');
        $this->assertSame('', $count->get('selectedTemplateId'), 'The picker resets, so the same template can be loaded again.');

        // Loading it a second time adds nothing and says so.
        $count->set('selectedTemplateId', (string) $template->id)->call('loadTemplate');
        $this->assertCount(2, $count->get('lines'));
    }

    public function test_a_template_of_another_type_is_not_offered_to_a_count(): void
    {
        FormTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Dry store stock take',
            'form_type' => 'stock_take', 'is_active' => true, 'sort_order' => 0,
        ]);

        Livewire::test(CountForm::class)->assertDontSee('Dry store stock take');
    }
}
