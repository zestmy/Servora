<?php

namespace Tests\Feature;

use App\Livewire\Settings\StatutoryRates;
use App\Models\Company;
use App\Models\StatutorySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Everything on the Statutory Rates screen actually saves.
 *
 * REPORTED AS: ticking "HRD Corp levy" and pressing Save did nothing — no
 * error, the box just came back unticked. `hrdf_enabled` was loaded into the
 * form and rendered as a checkbox but was missing from the array written back,
 * so the setting could not be changed at all. The SKBBK rate and ceiling had
 * the same hole.
 *
 * A field absent from the save payload is not a compile error and not a
 * failing test — it is a control that silently does nothing. So this walks
 * EVERY switch and EVERY rate on the screen and asserts a changed value comes
 * back, rather than testing the two that happened to break.
 */
class StatutoryRatesSaveTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    /** Every on/off switch the screen renders. */
    private const FLAGS = [
        'epf_enabled', 'socso_enabled', 'eis_enabled',
        'pcb_enabled', 'hrdf_enabled', 'skbbk_enabled',
    ];

    /** Every numeric rate the screen renders, with a value to write. */
    private const RATES = [
        'epf_employee_rate' => 12.5, 'epf_employer_rate_low' => 13.5,
        'socso_employee_rate' => 0.6, 'socso_ceiling' => 5500,
        'eis_employee_rate' => 0.3, 'eis_ceiling' => 5800,
        'skbbk_employee_rate' => 1.25, 'skbbk_ceiling' => 5953.33,
        'hrdf_employer_rate' => 1.5,
        'pcb_relief_individual' => 9500,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Rates Co', 'slug' => Str::slug('Rates Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('settings.statutory', 'web');
        $this->user->givePermissionTo('settings.statutory');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** The reported bug. */
    public function test_ticking_the_hrd_corp_levy_saves_it(): void
    {
        Livewire::actingAs($this->user)->test(StatutoryRates::class)
            ->set('hrdf_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(
            (bool) StatutorySetting::forCompany($this->company->id)->fresh()->hrdf_enabled,
            'Ticking HRD Corp levy and saving must actually switch it on.'
        );
    }

    public function test_unticking_it_saves_too(): void
    {
        StatutorySetting::forCompany($this->company->id)->fill(['hrdf_enabled' => true])->save();

        Livewire::actingAs($this->user)->test(StatutoryRates::class)
            ->set('hrdf_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) StatutorySetting::forCompany($this->company->id)->fresh()->hrdf_enabled);
    }

    /**
     * The general form of the bug: any switch that is rendered but missing
     * from the payload. Walks all of them so the next one added is caught.
     */
    public function test_every_switch_on_the_screen_round_trips(): void
    {
        foreach (self::FLAGS as $flag) {
            // Set every flag to a known state, flipping the one under test, so
            // a payload that writes a hard-coded value is caught as well as
            // one that omits the key.
            foreach ([true, false] as $target) {
                Livewire::actingAs($this->user)->test(StatutoryRates::class)
                    ->set($flag, $target)
                    ->call('save')
                    ->assertHasNoErrors();

                $this->assertSame(
                    $target,
                    (bool) StatutorySetting::forCompany($this->company->id)->fresh()->{$flag},
                    "$flag was rendered on the screen but did not save as " . var_export($target, true) . '.'
                );
            }
        }
    }

    /** Same again for the numbers, which had the same hole for SKBBK. */
    public function test_every_rate_on_the_screen_round_trips(): void
    {
        $component = Livewire::actingAs($this->user)->test(StatutoryRates::class);

        foreach (self::RATES as $field => $value) {
            $component->set($field, (string) $value);
        }

        $component->call('save')->assertHasNoErrors();

        $saved = StatutorySetting::forCompany($this->company->id)->fresh();

        foreach (self::RATES as $field => $value) {
            $this->assertEqualsWithDelta(
                $value, (float) $saved->{$field}, 0.01,
                "$field was rendered on the screen but did not save."
            );
        }
    }

    /** Reopening the screen must show what was saved, not the defaults. */
    public function test_the_screen_reloads_what_it_saved(): void
    {
        Livewire::actingAs($this->user)->test(StatutoryRates::class)
            ->set('hrdf_enabled', true)
            ->set('skbbk_enabled', true)
            ->set('skbbk_ceiling', '5953.33')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($this->user)->test(StatutoryRates::class)
            ->assertSet('hrdf_enabled', true)
            ->assertSet('skbbk_enabled', true)
            ->assertSet('skbbk_ceiling', '5953.33');
    }
}
