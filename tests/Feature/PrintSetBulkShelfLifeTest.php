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
                'label_type'    => 'prep',
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
            ->set('bulkShelfLifeMode', 'set')
            ->set('bulkShelfLifeValue', '3')
            ->set('bulkShelfLifeUnit', 'days')
            ->call('applyBulk');

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
            ->set('bulkShelfLifeMode', 'set')
            ->set('bulkShelfLifeValue', '12')
            ->set('bulkShelfLifeUnit', 'hours')
            ->call('applyBulk');

        foreach ($this->lines as $line) {
            $this->assertEquals(12.0, (float) $line->fresh()->shelf_life_value);
            $this->assertSame('hours', $line->fresh()->shelf_life_unit);
        }
    }

    /**
     * Clearing has to clear the unit too, or a line carries a unit with
     * nothing to measure — the same rule updateLine() already follows.
     */
    public function test_choosing_auto_puts_the_lines_back_on_auto(): void
    {
        foreach ($this->lines as $line) {
            $line->update(['shelf_life_value' => 5, 'shelf_life_unit' => 'days']);
        }

        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkShelfLifeMode', 'auto')
            ->call('applyBulk');

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
            ->set('bulkShelfLifeMode', 'set')
            ->set('bulkShelfLifeValue', '0')
            ->call('applyBulk');

        // '0' is not the same as empty: empty means Auto, zero means a use-by
        // date identical to the prepared time, which is never what was meant.
        $this->assertNull($this->lines[0]->fresh()->shelf_life_value);
    }

    public function test_applying_with_nothing_selected_changes_nothing(): void
    {
        $this->screen()
            ->set('selectedLines', [])
            ->set('bulkShelfLifeMode', 'set')
            ->set('bulkShelfLifeValue', '3')
            ->call('applyBulk');

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
            'sort_order' => 0, 'label_type' => 'prep', 'storage_state' => 'chill',
            'copies' => 1, 'is_active' => true,
        ]);

        $this->screen()
            ->set('selectedLines', [$this->lines[0]->id, $theirLine->id])
            ->set('bulkShelfLifeMode', 'set')
            ->set('bulkShelfLifeValue', '7')
            ->call('applyBulk');

        $this->assertEquals(7.0, (float) $this->lines[0]->fresh()->shelf_life_value);
        $this->assertNull($theirLine->fresh()->shelf_life_value, 'A line outside the open set was edited.');
    }

    /**
     * Lines carry no company scope of their own, so the per-line actions must
     * be pinned to the open set — otherwise a crafted request could change the
     * printed use-by on, or delete, another outlet's (or company's) line.
     */
    public function test_a_line_from_another_set_cannot_be_updated_or_removed_from_here(): void
    {
        $theirLine = $this->otherOutletLine();

        $this->screen()
            ->call('updateLine', $theirLine->id, 'storage_state', 'frozen')
            ->call('updateLine', $theirLine->id, 'label_type', 'oof')
            ->call('updateLine', $theirLine->id, 'shelf_life_value', '30')
            ->call('removeLine', $theirLine->id);

        $theirs = $theirLine->fresh();
        $this->assertNotNull($theirs, 'A line outside the open set was removed.');
        $this->assertSame('chill', $theirs->storage_state);
        $this->assertSame('prep', $theirs->label_type);
        $this->assertNull($theirs->shelf_life_value);
    }

    public function test_an_unknown_storage_state_or_label_type_is_refused(): void
    {
        $this->screen()
            ->call('updateLine', $this->lines[0]->id, 'storage_state', 'sunbathing')
            ->call('updateLine', $this->lines[0]->id, 'label_type', 'bogus');

        $line = $this->lines[0]->fresh();
        $this->assertSame('chill', $line->storage_state);
        $this->assertSame('prep', $line->label_type);
    }

    public function test_a_line_in_the_open_set_can_still_be_updated_and_removed(): void
    {
        $this->screen()
            ->call('updateLine', $this->lines[0]->id, 'storage_state', 'frozen')
            ->call('removeLine', $this->lines[1]->id);

        $this->assertSame('frozen', $this->lines[0]->fresh()->storage_state);
        $this->assertNull($this->lines[1]->fresh());
    }

    private function otherOutletLine(): LabelSetLine
    {
        $otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Third', 'code' => 'THI', 'is_active' => true,
        ]);
        $otherSet = LabelSet::create([
            'company_id' => $this->company->id, 'outlet_id' => $otherOutlet->id,
            'name' => 'Pastry', 'is_active' => true, 'created_by' => $this->user->id,
        ]);

        return LabelSetLine::create([
            'label_set_id' => $otherSet->id, 'custom_name' => 'NOT MINE',
            'sort_order' => 0, 'label_type' => 'prep', 'storage_state' => 'chill',
            'copies' => 1, 'is_active' => true,
        ]);
    }

    /**
     * The bar is conditional markup — it only exists once something is ticked
     * — so a passing behaviour test proves nothing about whether anybody can
     * reach it.
     */
    public function test_the_bulk_bar_appears_only_once_something_is_ticked(): void
    {
        $this->screen()
            ->assertDontSee('wire:click="applyBulk"', escape: false)
            ->set('selectedLines', $this->ids(0))
            ->assertSee('wire:click="applyBulk"', escape: false)
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

    public function test_label_type_storage_and_copies_apply_to_every_ticked_line(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkLabelType', 'oof')
            ->set('bulkStorageState', 'ambient')
            ->set('bulkCopies', '4')
            ->call('applyBulk');

        foreach ($this->lines as $line) {
            $fresh = $line->fresh();
            $this->assertSame('oof', $fresh->label_type);
            $this->assertSame('ambient', $fresh->storage_state, 'An explicit storage choice should beat the type default.');
            $this->assertSame(4, (int) $fresh->copies);
        }
    }

    /**
     * One Apply changing one column must leave the others alone — above all
     * a line's own shelf life, which used to be cleared by an empty box.
     */
    public function test_fields_left_on_no_change_are_untouched(): void
    {
        $this->lines[0]->update(['shelf_life_value' => 5, 'shelf_life_unit' => 'days', 'copies' => 2]);

        $this->screen()
            ->set('selectedLines', $this->ids(0))
            ->set('bulkStorageState', 'frozen')
            ->call('applyBulk');

        $fresh = $this->lines[0]->fresh();
        $this->assertSame('frozen', $fresh->storage_state);
        $this->assertSame('prep', $fresh->label_type);
        $this->assertSame(2, (int) $fresh->copies);
        $this->assertEquals(5.0, (float) $fresh->shelf_life_value);
        $this->assertSame('days', $fresh->shelf_life_unit);
    }

    public function test_copies_are_clamped_to_the_line_range(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkCopies', '500')
            ->call('applyBulk');

        $this->assertSame(99, (int) $this->lines[0]->fresh()->copies);
    }

    public function test_an_unknown_label_type_or_storage_state_is_refused(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkStorageState', 'lava')
            ->call('applyBulk');

        $this->assertSame('chill', $this->lines[0]->fresh()->storage_state);
    }

    /** Same rule as the per-line select: a new type brings its default storage. */
    public function test_changing_type_alone_resets_storage_to_that_types_default(): void
    {
        $this->screen()
            ->call('toggleAllLines')
            ->set('bulkLabelType', 'oof')
            ->call('applyBulk');

        $this->assertSame('oof', $this->lines[0]->fresh()->label_type);
        $this->assertSame('thawed', $this->lines[0]->fresh()->storage_state);
    }
}
