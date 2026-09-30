<?php

namespace Tests\Feature;

use App\Livewire\Recipes\Form as RecipeForm;
use App\Livewire\Recipes\Index as RecipesIndex;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Recipes list's route asks only for recipes.view, so its writes authorise
 * themselves; and the recipe form's image removal is pinned to the recipe being
 * edited, because RecipeImage has no company scope of its own.
 */
class RecipeWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->company = $this->company('Recipe Co');
        $this->uom = UnitOfMeasure::create(['name' => 'Portion', 'abbreviation' => 'ptn', 'type' => 'count']);
    }

    private function company(string $name): Company
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'M' . $company->id, 'is_active' => true,
        ]);

        return $company;
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities, ?Company $company = null): User
    {
        $company ??= $this->company;
        $user = User::factory()->create([
            'company_id' => $company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$company->id]);

        setPermissionsTeamId($company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        return $user;
    }

    private function recipe(Company $company, string $name = 'Nasi Lemak'): Recipe
    {
        return Recipe::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => $name,
            'yield_uom_id' => $this->uom->id, 'is_active' => true,
        ]);
    }

    public function test_a_view_only_user_cannot_toggle_a_recipe(): void
    {
        $viewer = $this->user(['recipes.view']);
        $recipe = $this->recipe($this->company);

        Livewire::actingAs($viewer)->test(RecipesIndex::class)
            ->call('toggleActive', $recipe->id)
            ->assertForbidden();

        $this->assertTrue($recipe->fresh()->is_active);
    }

    public function test_a_manager_can_toggle_a_recipe(): void
    {
        $manager = $this->user(['recipes.view', 'recipes.manage']);
        $recipe  = $this->recipe($this->company);

        Livewire::actingAs($manager)->test(RecipesIndex::class)
            ->call('toggleActive', $recipe->id)
            ->assertOk();

        $this->assertFalse($recipe->fresh()->is_active);
    }

    public function test_a_view_only_user_cannot_duplicate_reorder_or_bulk_delete(): void
    {
        $viewer = $this->user(['recipes.view']);
        $a = $this->recipe($this->company, 'A');
        $b = $this->recipe($this->company, 'B');

        Livewire::actingAs($viewer)->test(RecipesIndex::class)
            ->call('duplicate', $a->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(RecipesIndex::class)
            ->call('reorder', [(string) $b->id, (string) $a->id])
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(RecipesIndex::class)
            ->set('selectedIds', [(string) $a->id])
            ->call('bulkDelete')
            ->assertForbidden();

        $this->assertSame(2, Recipe::count());
        $this->assertNotSoftDeleted($a);
    }

    public function test_removing_an_image_cannot_reach_another_companys_recipe(): void
    {
        $other      = $this->company('Rival Co');
        $theirs     = $this->recipe($other, 'Their Dish');
        Storage::disk('public')->put('recipe-images/theirs.jpg', 'bytes');
        $theirImage = RecipeImage::create([
            'recipe_id' => $theirs->id, 'file_path' => 'recipe-images/theirs.jpg', 'sort_order' => 1,
            'file_name' => 'theirs.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        $manager = $this->user(['recipes.view', 'recipes.manage']);
        $mine    = $this->recipe($this->company, 'My Dish');

        Livewire::actingAs($manager)->test(RecipeForm::class, ['id' => $mine->id])
            ->call('removeExistingImage', $theirImage->id);

        $this->assertDatabaseHas('recipe_images', ['id' => $theirImage->id]);
        Storage::disk('public')->assertExists('recipe-images/theirs.jpg');
    }

    public function test_removing_an_image_still_works_on_the_recipe_being_edited(): void
    {
        $manager = $this->user(['recipes.view', 'recipes.manage']);
        $mine    = $this->recipe($this->company, 'My Dish');
        Storage::disk('public')->put('recipe-images/mine.jpg', 'bytes');
        $image = RecipeImage::create([
            'recipe_id' => $mine->id, 'file_path' => 'recipe-images/mine.jpg', 'sort_order' => 1,
            'file_name' => 'mine.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 5,
        ]);

        Livewire::actingAs($manager)->test(RecipeForm::class, ['id' => $mine->id])
            ->call('removeExistingImage', $image->id);

        $this->assertDatabaseMissing('recipe_images', ['id' => $image->id]);
    }
}
