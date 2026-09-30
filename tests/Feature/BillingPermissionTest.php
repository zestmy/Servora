<?php

namespace Tests\Feature;

use App\Livewire\Billing\Checkout;
use App\Livewire\Billing\Index as BillingIndex;
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
 * Billing changes what the whole company pays for, so it belongs to whoever
 * administers the company (users.manage) — not to every signed-in member.
 */
class BillingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Bill Co', 'slug' => 'bill-co-' . Str::random(6),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'outlet_id' => $this->outlet->id]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(array_map(fn ($a) => Permission::findOrCreate($a, 'web'), $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * Every upgrade link lands on billing.index, so it opens for everyone —
     * but without users.manage it only says who to ask: no plan, invoices,
     * coupon or checkout. Checkout itself stays closed.
     */
    public function test_a_user_without_users_manage_sees_ask_your_admin_not_the_plan(): void
    {
        $this->actingAs($this->user(['recipes.view']));

        $this->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Only your company admin can change the plan')
            ->assertDontSee('redeemCoupon');
        $this->get(route('billing.checkout', ['planSlug' => 'full']))->assertForbidden();
    }

    public function test_a_company_admin_can_open_billing(): void
    {
        $this->actingAs($this->user(['users.manage']));

        $this->get(route('billing.index'))->assertOk();
    }

    public function test_a_user_without_users_manage_cannot_redeem_a_coupon(): void
    {
        $this->actingAs($this->user(['recipes.view']));

        Livewire::test(BillingIndex::class)
            ->set('couponCode', 'FREEMONTH')
            ->call('redeemCoupon')
            ->assertForbidden();
    }

    public function test_a_user_without_users_manage_cannot_pay(): void
    {
        $this->actingAs($this->user(['recipes.view']));

        Livewire::test(Checkout::class, ['planSlug' => 'full'])
            ->call('pay')
            ->assertForbidden();
    }
}
