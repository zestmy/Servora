<?php

namespace Tests\Feature;

use App\Livewire\OutletSwitcher;
use App\Livewire\Settings\Users as UsersScreen;
use App\Models\CentralKitchen;
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
 * Every outlet can be granted to somebody.
 *
 * The user access screen splits outlets in two: the ordinary ones get
 * checkboxes, and a central kitchen's base outlet is offered under Central
 * Kitchen Access instead, because ticking it there also grants the
 * kitchen_users membership that makes the CK workspace reachable. Granting the
 * outlet alone would produce a user who can see the kitchen's stock and cannot
 * switch into it.
 *
 * THE GAP: the exclusion asked about ANY kitchen and the kitchen block listed
 * only ACTIVE ones. Stand a kitchen down and its base outlet fell between
 * them — absent from the checkboxes, absent from the kitchen list, grantable
 * by nobody without reactivating the kitchen or editing the database.
 *
 * The two lists partition the outlets, and a partition with a gap is an outlet
 * nobody can reach, so both ask the same question now and dormant kitchens are
 * labelled rather than hidden.
 */
class InactiveKitchenOutletAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $shop;
    private Outlet $kitchenOutlet;
    private CentralKitchen $kitchen;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Access Co', 'slug' => Str::slug('Access Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->shop          = $this->outlet('KLCC', 'KLCC');
        $this->kitchenOutlet = $this->outlet('CENTRAL KITCHEN', 'CK');

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK Cheras', 'code' => 'CKC',
            'outlet_id' => $this->kitchenOutlet->id, 'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->admin->companies()->syncWithoutDetaching([$this->company->id]);
        $this->admin->outlets()->sync([$this->shop->id, $this->kitchenOutlet->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('users.manage', 'web');
        $this->admin->givePermissionTo('users.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /** @return array{outlets: array<int,string>, kitchens: array<int,string>} */
    private function whatTheScreenOffers(): array
    {
        $c = Livewire::actingAs($this->admin)->test(UsersScreen::class);

        return [
            'outlets'  => $c->viewData('regularOutlets')->pluck('name')->sort()->values()->all(),
            'kitchens' => $c->viewData('kitchens')->pluck('name')->sort()->values()->all(),
        ];
    }

    // ── The gap ───────────────────────────────────────────────────────────

    /** The reported case: nothing may fall between the two lists. */
    public function test_a_stood_down_kitchens_outlet_is_still_grantable(): void
    {
        $this->kitchen->update(['is_active' => false]);

        $offered = $this->whatTheScreenOffers();

        $this->assertSame(['CK Cheras'], $offered['kitchens'],
            'Its outlet is kept out of the checkboxes, so the kitchen has to stay on this list.');
    }

    /** Stated as the property rather than the case: no outlet is unreachable. */
    public function test_every_active_outlet_appears_in_exactly_one_list(): void
    {
        $this->kitchen->update(['is_active' => false]);
        $this->outlet('IOI', 'IOI');

        $c = Livewire::actingAs($this->admin)->test(UsersScreen::class);

        $offered = $c->viewData('regularOutlets')->pluck('id')
            ->merge($c->viewData('kitchens')->pluck('outlet_id'))
            ->filter()->map(fn ($id) => (int) $id)->sort()->values()->all();

        $all = Outlet::where('company_id', $this->company->id)->where('is_active', true)
            ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($all, $offered, 'An outlet in neither list can be granted to nobody.');
    }

    /** And it can actually be granted, not merely displayed. */
    public function test_granting_the_stood_down_kitchen_attaches_its_outlet(): void
    {
        $this->kitchen->update(['is_active' => false]);

        $staff = User::factory()->create(['company_id' => $this->company->id]);
        $staff->companies()->syncWithoutDetaching([$this->company->id]);

        Livewire::actingAs($this->admin)->test(UsersScreen::class)
            ->call('openEdit', $staff->id)
            ->set('outletMode', 'selected')
            ->set('outletIds', [])
            ->set('kitchenMode', 'selected')
            ->set('kitchenIds', [$this->kitchen->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertContains($this->kitchenOutlet->id, $staff->fresh()->outlets->pluck('id')->all(),
            'Ticking the kitchen must attach its base outlet, which is the whole reason it is offered there.');
    }

    // ── The same shape in the outlet switcher ─────────────────────────────

    /**
     * The switcher splits the same way and had the same gap: the ordinary half
     * rejected kitchen outlets outright while the kitchen half was gated on
     * being a kitchen user. Somebody holding a kitchen's base outlet without
     * the kitchen_users row had it in neither — granted an outlet they could
     * not switch to.
     */
    public function test_the_switcher_offers_a_kitchen_outlet_to_whoever_holds_it(): void
    {
        $staff = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $staff->companies()->syncWithoutDetaching([$this->company->id]);
        $staff->outlets()->sync([$this->shop->id, $this->kitchenOutlet->id]);

        $this->assertFalse($staff->isKitchenUser(), 'The fixture must be the case that broke.');

        $c = Livewire::actingAs($staff)->test(OutletSwitcher::class);

        $offered = $c->viewData('outlets')->pluck('name')
            ->merge($c->viewData('kitchenOutlets')->pluck('name'))
            ->sort()->values()->all();

        $this->assertSame(['CENTRAL KITCHEN', 'KLCC'], $offered,
            'An outlet they hold and cannot select is a grant that does nothing.');
    }

    /** And it stays a partition — nothing listed twice. */
    public function test_the_switcher_lists_each_outlet_once(): void
    {
        $c = Livewire::actingAs($this->admin)->test(OutletSwitcher::class);

        $ordinary = $c->viewData('outlets')->pluck('id');
        $kitchens = $c->viewData('kitchenOutlets')->pluck('id');

        $this->assertEmpty($ordinary->intersect($kitchens),
            'An outlet in both halves would render twice.');
    }

    /** Somebody with no kitchen outlet still sees no kitchen section. */
    public function test_the_switcher_shows_no_kitchen_section_to_someone_without_one(): void
    {
        $staff = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $staff->companies()->syncWithoutDetaching([$this->company->id]);
        $staff->outlets()->sync([$this->shop->id]);

        $this->assertCount(0, Livewire::actingAs($staff)->test(OutletSwitcher::class)->viewData('kitchenOutlets'));
    }

    // ── Unchanged behaviour ───────────────────────────────────────────────

    /** An active kitchen still keeps its outlet out of the ordinary list. */
    public function test_an_active_kitchens_outlet_is_still_not_an_ordinary_checkbox(): void
    {
        $offered = $this->whatTheScreenOffers();

        $this->assertSame(['KLCC'], $offered['outlets'],
            'Granting it as a plain outlet would give stock access with no way into the workspace.');
        $this->assertSame(['CK Cheras'], $offered['kitchens']);
    }

    /** Active kitchens sort first, so standing one down does not reorder the list. */
    public function test_active_kitchens_are_listed_before_dormant_ones(): void
    {
        $spare = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'CK TWO', 'code' => 'CK2', 'is_active' => true,
        ]);
        CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'AAA Dormant', 'code' => 'AAA',
            'outlet_id' => $spare->id, 'is_active' => false,
        ]);

        $names = Livewire::actingAs($this->admin)->test(UsersScreen::class)
            ->viewData('kitchens')->pluck('name')->all();

        $this->assertSame(['CK Cheras', 'AAA Dormant'], $names,
            'Alphabetically AAA would come first; the working kitchen should lead.');
    }
}
