<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A list screen comes back on the outlet you left it on.
 *
 * Asked for after the same fix on Employees: open a record, save it, and the
 * list returns having forgotten which branch you were working through. These
 * modules lose it a different way from Employees — the filter starts on All
 * rather than defaulting to your own outlet — but the effect on somebody
 * working through one branch is identical.
 *
 * Remembered per screen rather than threaded through the forms: Inventory has
 * five forms behind it and Purchasing seven, and passing a parameter into each
 * and back out of every save, cancel and back arrow is a lot of surface to get
 * wrong for a convenience.
 */
class RemembersOutletFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $a;
    private Outlet $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Recall Co', 'slug' => Str::slug('Recall Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->a = $this->outlet('Alpha', 'ALP');
        $this->b = $this->outlet('Bravo', 'BRV');
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->a->id, $this->b->id]);

        setPermissionsTeamId($this->company->id);

        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability, 'web');
        }

        $user->givePermissionTo($abilities);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** @return array<string, array{0: class-string, 1: array<int, string>}> */
    public static function screenProvider(): array
    {
        return [
            'stock management' => [\App\Livewire\Inventory\Index::class, ['inventory.view']],
            'purchasing'       => [\App\Livewire\Purchasing\Index::class, ['purchasing.view']],
            'sales'            => [\App\Livewire\Sales\Index::class, ['sales.view']],
            // Recipes is deliberately absent: it already remembers all of its
            // filters PER TAB, which is finer-grained than this trait, and a
            // second mechanism for the same thing is how the two drift apart.
        ];
    }

    /**
     * @dataProvider screenProvider
     *
     * @param  class-string  $screen
     * @param  array<int, string>  $abilities
     */
    public function test_the_outlet_is_still_there_next_time(string $screen, array $abilities): void
    {
        $user = $this->user($abilities);

        // Pick a branch, as somebody working through it would.
        Livewire::actingAs($user)->test($screen)->set('outletFilter', (string) $this->b->id);

        // Come back — from a save, a cancel, or the menu. Same screen, same branch.
        Livewire::actingAs($user)->test($screen)
            ->assertSet('outletFilter', (string) $this->b->id);
    }

    /**
     * "All outlets" is a choice, not the absence of one. Storing it is what
     * lets a screen tell "they widened it" from "they have not said".
     *
     * @dataProvider screenProvider
     *
     * @param  class-string  $screen
     * @param  array<int, string>  $abilities
     */
    public function test_choosing_all_outlets_is_remembered_too(string $screen, array $abilities): void
    {
        $user = $this->user($abilities);

        Livewire::actingAs($user)->test($screen)->set('outletFilter', (string) $this->a->id);
        Livewire::actingAs($user)->test($screen)->set('outletFilter', '');

        Livewire::actingAs($user)->test($screen)->assertSet('outletFilter', '');
    }

    /**
     * Per screen. Filtering Purchasing says nothing about how you want Stock
     * Management filtered, and one shared key would have every screen lurch to
     * whichever you touched last.
     */
    public function test_each_screen_remembers_its_own(): void
    {
        $user = $this->user(['inventory.view', 'purchasing.view']);

        Livewire::actingAs($user)->test(\App\Livewire\Inventory\Index::class)
            ->set('outletFilter', (string) $this->a->id);

        Livewire::actingAs($user)->test(\App\Livewire\Purchasing\Index::class)
            ->set('outletFilter', (string) $this->b->id);

        Livewire::actingAs($user)->test(\App\Livewire\Inventory\Index::class)
            ->assertSet('outletFilter', (string) $this->a->id);

        Livewire::actingAs($user)->test(\App\Livewire\Purchasing\Index::class)
            ->assertSet('outletFilter', (string) $this->b->id);
    }

    /**
     * A remembered id the user can no longer reach must not widen anything.
     * It cannot: selectedOutletId() resolves an out-of-reach value to null and
     * the base scope still bounds every query to accessible outlets. Stated
     * here because a remembered value outlives the access that set it.
     */
    public function test_a_remembered_outlet_outside_your_access_is_ignored(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $elsewhere = Outlet::create([
            'company_id' => $other->id, 'name' => 'Charlie', 'code' => 'CHR', 'is_active' => true,
        ]);

        $user = $this->user(['inventory.view']);

        session(['outlet-filter.' . \App\Livewire\Inventory\Index::class => (string) $elsewhere->id]);

        Livewire::actingAs($user)->test(\App\Livewire\Inventory\Index::class)->assertOk();
    }
}
