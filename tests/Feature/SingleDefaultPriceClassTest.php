<?php

namespace Tests\Feature;

use App\Livewire\Settings\PriceClasses;
use App\Models\Company;
use App\Models\RecipePriceClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * One default price class per list.
 *
 * FOUND ON PRODUCTION: two outlet classes were flagged default — "Price
 * (3/7/26)" with 317 prices against it and "KLCC" with 40 — and the callers
 * did not agree which won. SmartImport reads ->where('is_default')->first(),
 * which is primary-key order and lands on the first; the recipe form reads
 * ordered()->firstWhere(), which is sort_order then name and lands on the
 * other. Same data, two answers, on two screens.
 *
 * The settings screen already cleared the previous default, so the state
 * arrived some other way — a seeder, an import, a console fix. A rule only one
 * entry point applies is not a rule, so it moved onto the model.
 */
class SingleDefaultPriceClassTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Default Co', 'slug' => Str::slug('Default Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('recipes.price', 'web');
        $this->user->givePermissionTo('recipes.price');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function class(string $name, string $scope, bool $default = false, int $sort = 0): RecipePriceClass
    {
        return RecipePriceClass::create([
            'company_id' => $this->company->id, 'scope' => $scope,
            'name' => $name, 'sort_order' => $sort, 'is_default' => $default,
        ]);
    }

    /** @return array<int, string> names flagged default in that scope */
    private function defaults(string $scope): array
    {
        return RecipePriceClass::inScope($scope)->where('is_default', true)
            ->orderBy('name')->pluck('name')->all();
    }

    // ── The rule ──────────────────────────────────────────────────────────

    public function test_marking_a_new_default_clears_the_old_one(): void
    {
        $this->class('Price (3/7/26)', RecipePriceClass::SCOPE_OUTLET, true);
        $this->class('KLCC', RecipePriceClass::SCOPE_OUTLET, true);

        $this->assertSame(['KLCC'], $this->defaults(RecipePriceClass::SCOPE_OUTLET),
            'The one marked last wins, and it must be the only one.');
    }

    /** The path production's state could not have come through, and still cannot. */
    public function test_it_holds_when_a_seeder_writes_directly(): void
    {
        $first = $this->class('First', RecipePriceClass::SCOPE_OUTLET, true);

        RecipePriceClass::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'scope' => RecipePriceClass::SCOPE_OUTLET,
            'name' => 'Second', 'sort_order' => 0, 'is_default' => true,
        ]);

        $this->assertFalse((bool) $first->fresh()->is_default);
        $this->assertSame(['Second'], $this->defaults(RecipePriceClass::SCOPE_OUTLET));
    }

    /** Promoting an existing class demotes the incumbent. */
    public function test_promoting_an_existing_class_demotes_the_incumbent(): void
    {
        $old = $this->class('Old', RecipePriceClass::SCOPE_OUTLET, true);
        $new = $this->class('New', RecipePriceClass::SCOPE_OUTLET);

        $new->update(['is_default' => true]);

        $this->assertFalse((bool) $old->fresh()->is_default);
        $this->assertSame(['New'], $this->defaults(RecipePriceClass::SCOPE_OUTLET));
    }

    // ── What it must not reach across ─────────────────────────────────────

    /** Per LIST: the kitchen's default is not the outlet's business. */
    public function test_a_kitchen_default_leaves_the_outlet_default_alone(): void
    {
        $this->class('Dine In', RecipePriceClass::SCOPE_OUTLET, true);
        $this->class('Wholesale', RecipePriceClass::SCOPE_KITCHEN, true);

        $this->assertSame(['Dine In'], $this->defaults(RecipePriceClass::SCOPE_OUTLET));
        $this->assertSame(['Wholesale'], $this->defaults(RecipePriceClass::SCOPE_KITCHEN));
    }

    /** And per COMPANY, which the global scope alone would not guarantee here. */
    public function test_one_companys_default_leaves_anothers_alone(): void
    {
        $ours = $this->class('Ours', RecipePriceClass::SCOPE_OUTLET, true);

        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        RecipePriceClass::withoutGlobalScopes()->create([
            'company_id' => $other->id, 'scope' => RecipePriceClass::SCOPE_OUTLET,
            'name' => 'Theirs', 'sort_order' => 0, 'is_default' => true,
        ]);

        $this->assertTrue((bool) $ours->fresh()->is_default,
            'Another tenant marking a default must not clear ours.');
    }

    /** Saving a non-default changes nothing — the hook only fires on true. */
    public function test_saving_a_non_default_leaves_the_default_standing(): void
    {
        $default = $this->class('Default', RecipePriceClass::SCOPE_OUTLET, true);
        $other   = $this->class('Other', RecipePriceClass::SCOPE_OUTLET);

        $other->update(['sort_order' => 5]);

        $this->assertTrue((bool) $default->fresh()->is_default);
    }

    // ── Through the screen ────────────────────────────────────────────────

    public function test_the_settings_screen_still_moves_the_default(): void
    {
        $old = $this->class('Old', RecipePriceClass::SCOPE_OUTLET, true);

        Livewire::actingAs($this->user)->test(PriceClasses::class)
            ->call('openCreate')
            ->set('name', 'New')
            ->set('sort_order', '1')
            ->set('is_default', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['New'], $this->defaults(RecipePriceClass::SCOPE_OUTLET));
        $this->assertFalse((bool) $old->fresh()->is_default);
    }
}
