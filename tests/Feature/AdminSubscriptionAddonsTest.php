<?php

namespace Tests\Feature;

use App\Livewire\Admin\Subscriptions\Index;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Until checkout sells add-ons, support grants them from Admin › Subscriptions.
 * The form is thin; SubscriptionService::syncAddons holds the rules, and a
 * refused add-on must not leave the subscription half-saved.
 */
class AdminSubscriptionAddonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_grants_add_ons_on_a_basic_subscription(): void
    {
        $sub = $this->basicSubscription();

        Livewire::test(Index::class)
            ->call('openEdit', $sub->id)
            ->set('sub_addons.labels.on', true)
            ->set('sub_addons.hr.on', true)
            ->set('sub_addons.hr.quantity', 18)
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['labels', 'hr'], $sub->addons()->pluck('module')->all());
        $this->assertSame(18, $sub->addons()->where('module', 'hr')->value('quantity'));

        // Re-opening shows what is on, and unticking removes it.
        Livewire::test(Index::class)
            ->call('openEdit', $sub->id)
            ->assertSet('sub_addons.labels.on', true)
            ->set('sub_addons.labels.on', false)
            ->call('saveSubscription');

        $this->assertSame(['hr'], $sub->addons()->pluck('module')->all());
    }

    public function test_a_third_add_on_on_basic_is_refused_and_nothing_is_saved(): void
    {
        $sub = $this->basicSubscription();

        Livewire::test(Index::class)
            ->call('openEdit', $sub->id)
            ->set('sub_status', 'past_due')
            ->set('sub_addons.labels.on', true)
            ->set('sub_addons.assets.on', true)
            ->set('sub_addons.audits.on', true)
            ->call('saveSubscription')
            ->assertHasErrors('sub_addons');

        $this->assertSame(0, $sub->addons()->count());
        $this->assertSame('active', $sub->fresh()->status, 'the subscription edit rolled back with it');
    }

    private function basicSubscription(): Subscription
    {
        $this->actingAs(User::factory()->create());

        $company = Company::create(['name' => 'Addon Co', 'slug' => 'addon-'.Str::random(6), 'currency' => 'MYR', 'is_active' => true]);

        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => Plan::where('slug', 'basic')->value('id'),
            'status' => 'active', 'billing_cycle' => 'monthly',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
    }
}
