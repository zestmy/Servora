<?php

namespace Tests\Feature;

use App\Livewire\Recipes\Index as RecipesIndex;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
