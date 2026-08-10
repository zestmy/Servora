<?php

namespace Tests\Feature;

use App\Livewire\Settings\Outlets as OutletsScreen;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A branch you just created has to be a branch you can use.
 *
 * Outlet access is an explicit per-user list, and creating a branch used to
 * attach it to nobody. The result was a branch that existed but appeared in no
 * outlet dropdown in the product — reported as a new branch missing from the
 * employee form's picker, but every screen reads the same
 * `User::accessibleOutlets()`, so the picker was just where it was noticed.
 *
 * Attaching only the creator is not enough and that is the subtle half: two
 * people who each held all four branches would end up holding four of five, and
 * nothing on screen would say the newest one existed. So the rule is that
 * complete coverage stays complete — and that it stops there, because a branch
 * manager on one outlet must not inherit a second one because head office opened
 * it.
 */
class OutletCreationAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $owner;      // holds every branch
    private User $manager;    // holds one branch
    private Outlet $first;
    private Outlet $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Branch Test Co',
            'slug'      => Str::slug('Branch Test Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->first  = $this->makeOutlet('First', 'ONE');
        $this->second = $this->makeOutlet('Second', 'TWO');

        $this->owner   = $this->makeUser('owner');
        $this->manager = $this->makeUser('manager');

        $this->owner->outlets()->sync([$this->first->id, $this->second->id]);
        $this->manager->outlets()->sync([$this->first->id]);
    }

    private function makeOutlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id,
            'name'       => $name,
            'code'       => $code,
            'is_active'  => true,
        ]);
    }

    private function makeUser(string $handle): User
    {
        $user = User::factory()->create([
            'company_id'           => $this->company->id,
            'email'                => $handle . '-' . uniqid() . '@example.test',
            'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        return $user;
    }

    private function createBranchAs(User $actor, string $name, string $code): Outlet
    {
        Livewire::actingAs($actor)->test(OutletsScreen::class)
            ->call('openCreate')
            ->set('name', $name)
            ->set('code', $code)
            ->call('save');

        return Outlet::where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    private function holds(User $user, Outlet $outlet): bool
    {
        return DB::table('outlet_user')
            ->where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->exists();
    }

    public function test_the_person_who_creates_a_branch_can_see_it(): void
    {
        $third = $this->createBranchAs($this->owner, 'Third', 'THREE');

        $this->assertTrue(
            $this->holds($this->owner, $third),
            'Creating a branch you cannot then select is the bug this fixes.'
        );
        $this->assertContains($third->id, $this->owner->fresh()->accessibleOutletIds());
    }

    public function test_complete_coverage_stays_complete(): void
    {
        $other = $this->makeUser('ops');
        $other->outlets()->sync([$this->first->id, $this->second->id]);

        $third = $this->createBranchAs($this->owner, 'Third', 'THREE');

        $this->assertTrue(
            $this->holds($other, $third),
            'Someone who held every branch must not silently drop to all-but-the-newest.'
        );
    }

    public function test_a_single_branch_manager_does_not_inherit_the_new_one(): void
    {
        $third = $this->createBranchAs($this->owner, 'Third', 'THREE');

        $this->assertFalse(
            $this->holds($this->manager, $third),
            'Head office opening a branch is not a promotion.'
        );
    }

    /**
     * The flag is the other way of saying "every branch", and it needs no rows.
     * Writing them anyway would make the pivot look like the source of an access
     * the flag actually decides.
     */
    public function test_an_all_outlets_account_gains_no_pivot_row(): void
    {
        $everywhere = $this->makeUser('everywhere');
        $everywhere->update(['can_view_all_outlets' => true]);
        $everywhere->outlets()->sync([]);

        $third = $this->createBranchAs($this->owner, 'Third', 'THREE');

        $this->assertFalse($this->holds($everywhere, $third));
        $this->assertContains(
            $third->id,
            $everywhere->fresh()->accessibleOutletIds(),
            'They see it through the flag, which is the point of the flag.'
        );
    }

    public function test_the_first_branch_of_a_company_goes_to_its_creator(): void
    {
        $fresh = Company::create([
            'name'      => 'Empty Co',
            'slug'      => Str::slug('Empty Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $founder = User::factory()->create(['company_id' => $fresh->id, 'can_view_all_outlets' => false]);
        $founder->companies()->syncWithoutDetaching([$fresh->id]);

        Livewire::actingAs($founder)->test(OutletsScreen::class)
            ->call('openCreate')
            ->set('name', 'Only')
            ->set('code', 'ONLY')
            ->call('save');

        $only = Outlet::where('company_id', $fresh->id)->where('code', 'ONLY')->firstOrFail();

        $this->assertTrue(
            $this->holds($founder, $only),
            'With no other branch to compare against, coverage cannot identify anyone — the creator still gets it.'
        );
    }
}
