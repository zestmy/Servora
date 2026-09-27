<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Navigation\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The supplier portal and marketplace are parked while Servora focuses on
 * merchants (config/modules.php). Parked means unreachable AND unadvertised:
 * a route that 404s behind a link that is still in the nav is worse than
 * either on its own. Supplier records and purchasing are merchant features
 * and must keep working.
 */
class SupplierPortalParkedTest extends TestCase
{
    use RefreshDatabase;

    public static function parkedPathProvider(): array
    {
        return [
            'marketplace'      => ['/marketplace'],
            'for suppliers'    => ['/for-suppliers'],
            'supplier login'   => ['/supplier/login'],
            'supplier signup'  => ['/supplier/register'],
            'supplier portal'  => ['/supplier/dashboard'],
        ];
    }

    /** @dataProvider parkedPathProvider */
    public function test_parked_public_pages_are_not_found(string $path): void
    {
        $this->get($path)->assertNotFound();
    }

    public function test_supplier_registration_cannot_be_posted(): void
    {
        $this->post('/supplier/register', ['name' => 'X'])->assertNotFound();
    }

    public function test_the_marketing_site_no_longer_links_to_them(): void
    {
        foreach (['/', '/features', '/pricing'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(route('marketplace'), $html, $path);
            $this->assertStringNotContainsString(route('for-suppliers'), $html, $path);
        }

        $this->get('/features')->assertDontSee('Supplier portal and marketplace');
    }

    public function test_merchant_screens_hide_product_mapping_and_the_directory(): void
    {
        $user = $this->merchant();
        Gate::before(fn () => true);

        $routes = collect(NavMenu::visible(NavMenu::outlet(), $user))
            ->flatMap(fn ($g) => $g['items'])->pluck('route');

        $this->assertNotContains('settings.supplier-mapping', $routes);
        $this->assertContains('settings.suppliers', $routes, 'supplier records are a merchant feature');

        $this->actingAs($user)->get(route('settings.supplier-mapping'))->assertNotFound();
        $this->actingAs($user)->get(route('purchasing.suppliers.directory'))->assertNotFound();
        $this->actingAs($user)->get(route('settings.suppliers'))->assertOk();
        $this->actingAs($user)->get(route('purchasing.index'))
            ->assertOk()
            ->assertDontSee(route('purchasing.suppliers.directory'), escape: false);
    }

    public function test_switching_the_module_back_on_restores_it(): void
    {
        config(['modules.supplier_portal' => true]);

        $this->get('/marketplace')->assertOk();
        $this->get('/for-suppliers')->assertOk();
        $this->get('/supplier/login')->assertOk();
        $this->get('/')->assertSee(route('marketplace'), escape: false);

        $user = $this->merchant();
        Gate::before(fn () => true);
        $routes = collect(NavMenu::visible(NavMenu::outlet(), $user))
            ->flatMap(fn ($g) => $g['items'])->pluck('route');
        $this->assertContains('settings.supplier-mapping', $routes);
    }

    private function merchant(): User
    {
        $company = Company::create([
            'name' => 'Parked Co', 'slug' => Str::slug('Parked Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->outlets()->syncWithoutDetaching([$outlet->id]);

        return $user;
    }
}
