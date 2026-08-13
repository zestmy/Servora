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
 * Every list screen with a form behind it comes back where you left it.
 *
 * REPORTED ON EMPLOYEES, then swept. The property under test is one thing:
 * filter a list, open a record, press Back, and the list is still filtered the
 * way you left it — not reset to your own outlet.
 *
 * Two mechanisms satisfy it and both are fine. Most screens use
 * RemembersOutletFilter, which keeps the choice in the session so the forms
 * need know nothing about it. Recipes keeps its own per-tab bundle of filters,
 * and Employees threads them through the URL because there is exactly one form
 * behind it. What matters is the outcome, so this tests the outcome.
 *
 * Compensation had neither and was found by this sweep: it overwrote the
 * outlet with the user's own on every mount, and reset the month to today.
 *
 * "All outlets" is checked separately because it is the case an empty string
 * cannot express — a screen that defaults to your own outlet must be able to
 * tell "they chose All" from "they chose nothing", and the ones that get this
 * wrong overrule the choice on the way back rather than losing it outright.
 */
class ListFilterRoundTripSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Screens whose outlet filter must survive a fresh mount. */
    private const SCREENS = [
        'Stock Management'   => \App\Livewire\Inventory\Index::class,
        'Purchasing'         => \App\Livewire\Purchasing\Index::class,
        'Invoices'           => \App\Livewire\Purchasing\InvoiceIndex::class,
        'Credit Notes'       => \App\Livewire\Purchasing\CreditNoteIndex::class,
        'Sales'              => \App\Livewire\Sales\Index::class,
        'Recipes'            => \App\Livewire\Recipes\Index::class,
        'Compensation'       => \App\Livewire\Hr\Compensation::class,
    ];

    private Company $company;
    private Outlet $own;
    private Outlet $other;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Round Co', 'slug' => Str::slug('Round Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        // The user's own outlet is deliberately the FIRST one, because that is
        // what a screen reverts to — so "kept" cannot pass by coincidence.
        $this->own   = $this->outlet('ALPHA', 'ALP');
        $this->other = $this->outlet('BETA', 'BET');

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->own->id, $this->other->id]);

        setPermissionsTeamId($this->company->id);
        foreach ([
            'inventory.view', 'purchasing.view', 'sales.view', 'recipes.view',
            'hr.view', 'hr.compensation',
        ] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /** Choose a filter, then mount afresh — which is what returning is. */
    private function roundTrip(string $class, string $chosen): string
    {
        Livewire::actingAs($this->user)->test($class)->set('outletFilter', $chosen);

        return (string) Livewire::actingAs($this->user)->test($class)->get('outletFilter');
    }

    public function test_every_list_comes_back_on_the_outlet_it_was_left_on(): void
    {
        $this->assertSame($this->own->id, $this->user->activeOutletId(),
            'The revert target must be ALPHA, or a kept BETA proves nothing.');

        foreach (self::SCREENS as $label => $class) {
            $this->assertSame((string) $this->other->id, $this->roundTrip($class, (string) $this->other->id),
                "{$label} reverted instead of coming back on the outlet it was left on.");
        }
    }

    /** The case an empty string cannot express. */
    public function test_choosing_all_outlets_is_not_overruled_on_the_way_back(): void
    {
        foreach (self::SCREENS as $label => $class) {
            $this->assertSame('', $this->roundTrip($class, ''),
                "{$label} overruled a deliberate \"All outlets\" with the user's own.");
        }
    }

    /**
     * Compensation is month-scoped, and a payroll list that returns on a
     * different period is not recognisably the same list.
     */
    public function test_compensation_comes_back_on_the_month_it_was_left_on(): void
    {
        $class = \App\Livewire\Hr\Compensation::class;
        $past  = now()->subMonths(3)->format('Y-m');

        Livewire::actingAs($this->user)->test($class)->set('month', $past);

        $this->assertSame($past,
            (string) Livewire::actingAs($this->user)->test($class)->get('month'),
            'Compensation reset the month to today on the way back.');
    }

    /** A first visit, with nothing stored, still defaults to your own outlet. */
    public function test_a_first_visit_still_defaults_to_the_users_own_outlet(): void
    {
        foreach ([\App\Livewire\Hr\Compensation::class, \App\Livewire\Sales\Index::class] as $class) {
            $this->assertSame(
                (string) $this->own->id,
                (string) Livewire::actingAs($this->user)->test($class)->get('outletFilter'),
                'With nothing remembered the screen should still open on your own outlet.'
            );
        }
    }
}
