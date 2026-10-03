<?php

namespace Tests\Feature;

use App\Http\Controllers\RecipeCostPdfController;
use App\Livewire\Recipes\Index as RecipesIndex;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Recipes / Prep Items "LMS" filter splits the list by what trainees can
 * actually see: the LMS lists only active items not excluded from it.
 */
class RecipeLmsFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'LMS Co', 'slug' => 'lms-co-' . Str::random(6),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'M1', 'is_active' => true]);
        $this->uom = UnitOfMeasure::create(['name' => 'Portion', 'abbreviation' => 'ptn', 'type' => 'count']);

        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->actingAs($user);
    }

    private function recipe(string $name, bool $prep, bool $active, bool $excluded): void
    {
        Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => $name, 'yield_uom_id' => $this->uom->id,
            'is_prep' => $prep, 'is_active' => $active, 'exclude_from_lms' => $excluded,
        ]);
    }

    public function test_recipes_tab_filters_by_lms_visibility(): void
    {
        $this->recipe('SHOWN RECIPE', false, true, false);
        $this->recipe('EXCLUDED RECIPE', false, true, true);
        $this->recipe('INACTIVE RECIPE', false, false, false);

        Livewire::test(RecipesIndex::class)
            ->set('lmsFilter', 'in')
            ->assertSee('SHOWN RECIPE')
            ->assertDontSee('EXCLUDED RECIPE')
            ->assertDontSee('INACTIVE RECIPE')
            ->set('lmsFilter', 'out')
            ->assertDontSee('SHOWN RECIPE')
            ->assertSee('EXCLUDED RECIPE')
            ->assertSee('INACTIVE RECIPE')
            ->set('lmsFilter', '')
            ->assertSee('SHOWN RECIPE')
            ->assertSee('EXCLUDED RECIPE');
    }

    public function test_prep_items_tab_filters_by_lms_visibility(): void
    {
        $this->recipe('SHOWN PREP', true, true, false);
        $this->recipe('EXCLUDED PREP', true, true, true);

        Livewire::test(RecipesIndex::class)
            ->set('tab', 'prep-items')
            ->set('lmsFilter', 'in')
            ->assertSee('SHOWN PREP')
            ->assertDontSee('EXCLUDED PREP')
            ->set('lmsFilter', 'out')
            ->assertDontSee('SHOWN PREP')
            ->assertSee('EXCLUDED PREP');
    }

    /** @return array<int, string> names the cost PDF / Excel exports would include */
    private function exported(array $params): array
    {
        $controller = app(RecipeCostPdfController::class);
        $apply = new \ReflectionMethod($controller, 'applyFilters');

        return $apply->invoke($controller, Recipe::where('is_prep', false), Request::create('/', 'GET', $params), false)
            ->orderBy('name')->pluck('name')->all();
    }

    public function test_exports_apply_the_lms_filter(): void
    {
        $this->recipe('Shown Recipe', false, true, false);
        $this->recipe('Excluded Recipe', false, true, true);
        $this->recipe('Inactive Recipe', false, false, false);

        $this->assertSame(['SHOWN RECIPE'], $this->exported(['lms' => 'in']));
        // Not overridden by the exports' implicit "active only" default.
        $this->assertSame(['EXCLUDED RECIPE', 'INACTIVE RECIPE'], $this->exported(['lms' => 'out']));
        $this->assertSame(['EXCLUDED RECIPE'], $this->exported(['lms' => 'out', 'status' => 'active']));
        // No LMS filter: the old default (active only) is unchanged.
        $this->assertSame(['EXCLUDED RECIPE', 'SHOWN RECIPE'], $this->exported([]));
    }

    public function test_export_links_carry_the_lms_filter(): void
    {
        $category = \App\Models\RecipeCategory::create([
            'company_id' => $this->company->id, 'name' => 'Mains', 'is_active' => true,
        ]);

        $html = Livewire::test(RecipesIndex::class)->set('lmsFilter', 'out')->html();

        $this->assertStringContainsString('lms=out', $html);
        // The By Category links carry it as well, escaped once (not "&amp;amp;").
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString(e(route('recipes.cost-pdf-all', ['category' => $category->id, 'lms' => 'out'])), $html);
    }

    public function test_by_category_links_carry_every_list_filter(): void
    {
        $category = \App\Models\RecipeCategory::create([
            'company_id' => $this->company->id, 'name' => 'Mains', 'is_active' => true,
        ]);
        $other = \App\Models\RecipeCategory::create([
            'company_id' => $this->company->id, 'name' => 'Drinks', 'is_active' => true,
        ]);
        $outlet = Outlet::where('company_id', $this->company->id)->first();

        $html = Livewire::test(RecipesIndex::class)
            ->set('search', 'nasi')
            ->set('categoryFilter', (string) $other->id)
            ->set('statusFilter', 'active')
            ->set('outletFilter', (string) $outlet->id)
            ->set('costFilter', 'over45')
            ->set('lmsFilter', 'in')
            ->html();

        // The link's own category wins over the list's.
        $this->assertStringContainsString(e(route('recipes.cost-pdf-all', [
            'category' => $category->id, 'search' => 'nasi', 'status' => 'active',
            'lms' => 'in', 'outlet' => $outlet->id, 'cost' => 'over45',
        ])), $html);
    }
}
