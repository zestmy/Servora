<?php

namespace Tests\Feature;

use App\Livewire\Labels\Sets;
use App\Models\Company;
use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Setting one shelf life across a whole print set in one go.
 *
 * A chiller set is a dozen items prepped the same morning that all last the
 * same three days. Typing that into twelve separate inputs is how it gets left
 * on "Auto" instead — and a set line left on Auto with no rule behind it makes
 * staff type the use-by date by hand, which is exactly what the label module
 * exists to stop.
 */
class PrintSetBulkShelfLifeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private LabelSet $set;
    /** @var array<int, LabelSetLine> */
    private array $lines = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Label Co', 'slug' => 'label-' . uniqid(), 'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $role = Role::findOrCreate('Label Manager', 'web');
        foreach (['labels.print', 'labels.manage'] as $ability) {
            $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
        }
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->set = LabelSet::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Chiller 1', 'is_active' => true, 'created_by' => $this->user->id,
        ]);

        foreach (['SAMBAL', 'MARINADE', 'CUT FRUIT'] as $i => $name) {
            $this->lines[] = LabelSetLine::create([
                'label_set_id' => $this->set->id,
                'custom_name'  => $name,
                'sort_order'   => $i,
                'label_type'    => 'use_by',
                'storage_state' => 'chill',
                'copies'        => 1,
                'is_active'    => true,
            ]);
        }
    }

    private function screen()
    {
        return Livewire::actingAs($this->user)
            ->test(Sets::class)
            ->set('outletId', $this->outlet->id)
            ->call('editLines', $this->set->id);
    }

    private function ids(int ...$indexes): array
    {
        return array_map(fn ($i) => $this->lines[$i]->id, $indexes);
    }

    public function test_one_shelf_life_can_be_applied_to_several_items_at_once(): void
    {
        $this->screen()
            ->set('selectedLines', $this->ids(0, 2))
            ->set('bulkShelfLifeValue', '3')
            ->set('bulkShelfLifeUnit', 'days')
            ->call('applyBulkShelfLife');

        $this->assertEquals(3.0, (float) $this->lines[0]->fresh()->shelf_life_value);
        $this->assertSame('days', $this->lines[0]->fresh()->shelf_life_unit);

        $this->assertEquals(3.0, (float) $this->lines[2]->fresh()->shelf_life_value);

        // The unticked one is untouched.
        $this->assertNull($this->lines[1]->fresh()->shelf_life_value);
    }

    public function test_select_all_ticks_every_line_and_a_second_press_clears_them(): void
    {
        $component = $this->screen()
            ->call('toggleAllLines')
            ->assertSet('selectedLines', $this->ids(0, 1, 2));

        $component->call('toggleAllLines')->assertSet('selectedLines', []);
    }

    public function test_select_all_then_apply_updates_the_whole_set(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkShelfLifeValue', '12')
            ->set('bulkShelfLifeUnit', 'hours')
            ->call('applyBulkShelfLife');

        foreach ($this->lines as $line) {
            $this->assertEquals(12.0, (float) $line->fresh()->shelf_life_value);
            $this->assertSame('hours', $line->fresh()->shelf_life_unit);
        }
    }

    /**
     * Clearing has to clear the unit too, or a line carries a unit with
     * nothing to measure — the same rule updateLine() already follows.
     */
    public function test_applying_an_empty_value_puts_the_lines_back_on_auto(): void
    {
        foreach ($this->lines as $line) {
            $line->update(['shelf_life_value' => 5, 'shelf_life_unit' => 'days']);
        }

        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkShelfLifeValue', '')
            ->call('applyBulkShelfLife');

        foreach ($this->lines as $line) {
            $this->assertNull($line->fresh()->shelf_life_value);
            $this->assertNull($line->fresh()->shelf_life_unit, 'A unit was left behind with no value.');
            $this->assertNull($line->fresh()->shelfLifeOverride(), 'The line should be following the rules again.');
        }
    }

    public function test_a_zero_or_negative_shelf_life_is_refused(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkShelfLifeValue', '0')
            ->call('applyBulkShelfLife');

        // '0' is not the same as empty: empty means Auto, zero means a use-by
        // date identical to the prepared time, which is never what was meant.
        $this->assertNull($this->lines[0]->fresh()->shelf_life_value);
    }

    public function test_applying_with_nothing_selected_changes_nothing(): void
    {
        $this->screen()
            ->set('selectedLines', [])
            ->set('bulkShelfLifeValue', '3')
            ->call('applyBulkShelfLife');

        foreach ($this->lines as $line) {
            $this->assertNull($line->fresh()->shelf_life_value);
        }

        // The component also flashes "tick the items first", because a button
        // that silently does nothing reads as the app being broken. Not
        // asserted here: Livewire's test harness does not surface a component
        // flash the way a full page request does — the same limitation
        // PurchasingDeleteGateTest writes down — and what matters is that
        // nothing was written.
    }

    /**
     * selectedLines is client-supplied. A line id from another outlet's set
     * must not be reachable — a wrong shelf life is a wrong use-by date on a
     * food-safety label.
     */
    public function test_a_line_from_another_set_cannot_be_edited_from_here(): void
    {
        $otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true,
        ]);
        $otherSet = LabelSet::create([
            'company_id' => $this->company->id, 'outlet_id' => $otherOutlet->id,
            'name' => 'Grill', 'is_active' => true, 'created_by' => $this->user->id,
        ]);
        $theirLine = LabelSetLine::create([
            'label_set_id' => $otherSet->id, 'custom_name' => 'NOT MINE',
            'sort_order' => 0, 'label_type' => 'use_by', 'storage_state' => 'chill',
            'copies' => 1, 'is_active' => true,
        ]);

        $this->screen()
            ->set('selectedLines', [$this->lines[0]->id, $theirLine->id])
            ->set('bulkShelfLifeValue', '7')
            ->call('applyBulkShelfLife');

        $this->assertEquals(7.0, (float) $this->lines[0]->fresh()->shelf_life_value);
        $this->assertNull($theirLine->fresh()->shelf_life_value, 'A line outside the open set was edited.');
    }

    /**
     * The bar is conditional markup — it only exists once something is ticked
     * — so a passing behaviour test proves nothing about whether anybody can
     * reach it.
     */
    public function test_the_bulk_bar_appears_only_once_something_is_ticked(): void
    {
        $this->screen()
            ->assertDontSee('applyBulkShelfLife')
            ->set('selectedLines', $this->ids(0))
            ->assertSee('applyBulkShelfLife', escape: false)
            ->assertSee('1 selected')
            ->assertSee('Apply to 1');
    }

    /** A tick list carried across sets would edit lines nobody can see. */
    public function test_switching_sets_clears_the_selection(): void
    {
        $otherSet = LabelSet::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'Sandwich Station', 'is_active' => true, 'created_by' => $this->user->id,
        ]);

        $this->screen()
            ->call('toggleAllLines')
            ->assertSet('selectedLines', $this->ids(0, 1, 2))
            ->call('editLines', $otherSet->id)
            ->assertSet('selectedLines', []);
    }
}
