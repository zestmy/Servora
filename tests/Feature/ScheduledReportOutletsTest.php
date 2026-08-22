<?php

namespace Tests\Feature;

use App\Livewire\Settings\ReportSubscriptions;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\ReportSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A scheduled report can cover a chosen set of outlets.
 *
 * It used to be one outlet or every outlet, because the subscription carried a
 * single nullable outlet_id. A manager looking after three branches of eleven
 * had to pick between eleven sections of noise and three separate emails.
 *
 * The set lives in a pivot, and EMPTY MEANS ALL — so a branch opened next
 * month still appears in a report that was set up to cover everything, which
 * would not be true if "all" had been stored as every id ticked.
 */
class ScheduledReportOutletsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    /** @var array<int, Outlet> */
    private array $outlets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Group Co', 'slug' => 'group-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);

        foreach (['Bangsar', 'Damansara', 'KLCC'] as $i => $name) {
            $this->outlets[] = Outlet::create([
                'company_id' => $this->company->id,
                'name'       => $name,
                'code'       => 'O' . $i,
                'is_active'  => true,
            ]);
        }

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlets[0]->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching(collect($this->outlets)->pluck('id')->all());

        setPermissionsTeamId($this->company->id);
        $role = Role::findOrCreate('Report Scheduler', 'web');
        $role->givePermissionTo(Permission::findOrCreate('reports.schedule', 'web'));
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function form()
    {
        return Livewire::actingAs($this->user)->test(ReportSubscriptions::class);
    }

    public function test_a_subscription_can_cover_several_named_outlets(): void
    {
        $chosen = [$this->outlets[0]->id, $this->outlets[2]->id];

        $this->form()
            ->call('openCreate')
            ->set('report_type', 'daily_sales')
            ->set('frequency', 'daily')
            ->set('outlet_ids', $chosen)
            ->call('save')
            ->assertHasNoErrors();

        $subscription = ReportSubscription::withoutGlobalScopes()->firstOrFail();

        $this->assertEqualsCanonicalizing($chosen, $subscription->outletIds());
        $this->assertFalse($subscription->coversAllOutlets());

        // outlet_id stays null for a SET, because it means "the one outlet".
        $this->assertNull($subscription->outlet_id);
    }

    /**
     * The derived column has to agree with the pivot, or a report covering one
     * outlet gets logged as if it covered everything.
     */
    public function test_choosing_exactly_one_outlet_also_sets_the_legacy_column(): void
    {
        $this->form()
            ->call('openCreate')
            ->set('outlet_ids', [$this->outlets[1]->id])
            ->call('save')
            ->assertHasNoErrors();

        $subscription = ReportSubscription::withoutGlobalScopes()->firstOrFail();

        $this->assertSame([$this->outlets[1]->id], $subscription->outletIds());
        $this->assertSame($this->outlets[1]->id, $subscription->outlet_id);
    }

    public function test_choosing_nothing_means_every_outlet(): void
    {
        $this->form()
            ->call('openCreate')
            ->set('outlet_ids', [])
            ->call('save')
            ->assertHasNoErrors();

        $subscription = ReportSubscription::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($subscription->coversAllOutlets());
        $this->assertNull($subscription->outlet_id);
        $this->assertSame('All Outlets', $subscription->outletLabel());
    }

    /**
     * "All" is an empty set precisely so it keeps meaning all. Storing every
     * current id would freeze the list at today's outlets.
     */
    public function test_an_all_outlets_subscription_picks_up_an_outlet_opened_later(): void
    {
        $subscription = ReportSubscription::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'report_type' => 'daily_sales', 'frequency' => 'daily',
            'delivery_channel' => 'email', 'delivery_time' => '06:00',
        ]);

        Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Mid Valley', 'code' => 'MV', 'is_active' => true,
        ]);

        $this->assertTrue($subscription->fresh()->coversAllOutlets());
    }

    public function test_editing_loads_the_existing_outlet_set(): void
    {
        $subscription = ReportSubscription::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'report_type' => 'daily_sales', 'frequency' => 'daily',
            'delivery_channel' => 'email', 'delivery_time' => '06:00',
        ]);
        $subscription->setOutlets([$this->outlets[0]->id, $this->outlets[1]->id]);

        $this->form()
            ->call('openEdit', $subscription->id)
            ->assertSet('outlet_ids', [$this->outlets[0]->id, $this->outlets[1]->id]);
    }

    /**
     * outlet_ids is a Livewire property, which means it is client-supplied.
     * Without a company check an id from another tenant would be synced
     * straight into the pivot and mailed that outlet's takings.
     */
    public function test_an_outlet_from_another_company_is_discarded(): void
    {
        $other = Company::create([
            'name' => 'Someone Else', 'slug' => 'other-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $theirOutlet = Outlet::create([
            'company_id' => $other->id, 'name' => 'Not Yours', 'code' => 'NY', 'is_active' => true,
        ]);

        $this->form()
            ->call('openCreate')
            ->set('outlet_ids', [$this->outlets[0]->id, $theirOutlet->id])
            ->call('save');

        $subscription = ReportSubscription::withoutGlobalScopes()->firstOrFail();

        $this->assertSame([$this->outlets[0]->id], $subscription->outletIds());
        $this->assertNotContains($theirOutlet->id, $subscription->outletIds());
    }

    public function test_the_label_summarises_a_long_set_rather_than_listing_it(): void
    {
        $subscription = ReportSubscription::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'report_type' => 'daily_sales', 'frequency' => 'daily',
            'delivery_channel' => 'email', 'delivery_time' => '06:00',
        ]);

        $subscription->setOutlets([$this->outlets[0]->id, $this->outlets[1]->id]);
        $this->assertSame('Bangsar, Damansara', $subscription->outletLabel());

        $subscription->setOutlets(collect($this->outlets)->pluck('id')->all());
        $this->assertSame('3 outlets', $subscription->outletLabel());
    }
}
