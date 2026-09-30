<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
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
 * A dashboard link is only offered to someone who can open it. A chef with
 * recipes.view alone used to be handed the expiring list, the GRN queue and
 * "New stock take" — every one of which answered 403.
 */
class DashboardLinkTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Dash Co', 'slug' => Str::slug('Dash Co') . '-' . uniqid(), 'currency' => 'MYR', 'is_active' => true]);
        $this->outlet  = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    }

    /** @param array<int, string> $permissions */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(collect($permissions)->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_a_recipes_view_only_chef_is_not_offered_links_they_cannot_open(): void
    {
        $chef = $this->userWith(['recipes.view']);
        $this->actingAs($chef);

        $html = Livewire::test(Dashboard::class)
            ->assertViewHas('dashboardType', 'chef')
            ->html();

        $this->assertStringNotContainsString(route('labels.expiring'), $html);
        $this->assertStringNotContainsString(route('purchasing.index'), $html);
        $this->assertStringNotContainsString(route('inventory.stock-takes.create'), $html);
        $this->assertStringNotContainsString(route('inventory.wastage.create'), $html);

        // What they CAN open is still there.
        $this->assertStringContainsString(route('recipes.index'), $html);
    }

    public function test_the_same_chef_with_the_abilities_gets_the_links_back(): void
    {
        $chef = $this->userWith(['recipes.view', 'labels.print', 'inventory.stock_takes.record']);
        $this->actingAs($chef);

        $html = Livewire::test(Dashboard::class)
            ->assertViewHas('dashboardType', 'chef')
            ->html();

        $this->assertStringContainsString(route('labels.expiring'), $html);
        $this->assertStringContainsString(route('inventory.stock-takes.create'), $html);
    }

    public function test_approve_po_is_refused_without_purchasing_approve(): void
    {
        $user = $this->userWith(['purchasing.view']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('approvePo', 1)
            ->assertForbidden();

        Livewire::test(Dashboard::class)
            ->call('rejectPo', 1)
            ->assertForbidden();
    }

    public function test_the_fallback_dashboard_hides_takings_from_someone_without_sales_view(): void
    {
        // Matches no specific branch, so lands on the manager dashboard.
        $user = $this->userWith(['labels.print']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertViewHas('dashboardType', 'manager')
            ->assertDontSee('Revenue today')
            ->assertDontSee('Pending POs')
            ->assertDontSee(route('purchasing.index'), false);
    }
}
