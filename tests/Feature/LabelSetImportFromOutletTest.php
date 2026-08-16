<?php

namespace Tests\Feature;

use App\Livewire\Labels\Sets;
use App\Livewire\Labels\Staff\Sets as StaffSets;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Copying a print set from one outlet into another.
 *
 * The outlets of one company mostly prep the same food, so a new branch's
 * "Chiller 1" is usually an existing branch's "Chiller 1" retyped — and
 * retyping is where items go missing. The copy exists on the manager screen
 * and in the staff PWA, and the staff path is the one worth being paranoid
 * about: it is the one write that app allows, made by a PIN session rather
 * than a user, so the company fence is rebuilt by hand there.
 *
 * Worth pinning: order and per-line settings survive the copy (unlike the
 * form import, both sides are print sets, so quantities carry too), a second
 * import tops up rather than doubles, and neither path can reach across a
 * company boundary.
 */
class LabelSetImportFromOutletTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $otherOutlet;
    private User $user;
    private UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Set Copy Co',
            'slug'      => Str::slug('Set Copy Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Damansara', 'code' => 'DMS', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['abbreviation' => 'kg'],
            ['name' => 'Kilogram', 'type' => 'weight']
        );
    }

    private function ingredient(string $name, ?Company $company = null): Ingredient
    {
        return Ingredient::create([
            'company_id'    => ($company ?? $this->company)->id,
            'name'          => $name,
            'base_uom_id'   => $this->uom->id,
            'recipe_uom_id' => $this->uom->id,
        ]);
    }

    /** A set in $outlet whose lines carry deliberately non-default settings. */
    private function sourceSet(Outlet $outlet, array $lines, array $overrides = []): LabelSet
    {
        $set = LabelSet::create(array_merge([
            'company_id'     => $outlet->company_id,
            'outlet_id'      => $outlet->id,
            'name'           => 'Chiller 1',
            'description'    => 'Walk-in, left to right',
            'show_storage'   => true,
            'storage_states' => ['chill'],
        ], $overrides));

        foreach ($lines as $i => $line) {
            LabelSetLine::create(array_merge([
                'label_set_id'  => $set->id,
                'sort_order'    => $i,
                'label_type'    => 'prep',
                'storage_state' => 'chill',
                'copies'        => 1,
            ], $line));
        }

        return $set;
    }

    private function managerScreen()
    {
        return Livewire::actingAs($this->user)->test(Sets::class)->set('outletId', $this->outlet->id);
    }

    // ── Manager screen ────────────────────────────────────────────────────

    public function test_a_set_copies_into_another_outlet_with_lines_settings_and_order(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');

        $this->sourceSet($this->otherOutlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id,
             'copies' => 3, 'quantity' => 2.5, 'uom_id' => $this->uom->id],
            ['custom_name' => 'Lemon Wedges', 'label_type' => 'opened', 'storage_state' => 'freeze'],
            ['labelable_type' => Ingredient::class, 'labelable_id' => $butter->id],
        ]);

        $this->managerScreen()
            ->call('openSetImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', LabelSet::firstOrFail()->id)
            ->call('importSet')
            ->assertHasNoErrors();

        $copy = LabelSet::where('outlet_id', $this->outlet->id)->firstOrFail();

        $this->assertSame('Chiller 1', $copy->name);
        $this->assertTrue((bool) $copy->show_storage);
        $this->assertSame(['chill'], $copy->storage_states, 'The QR label storage choice is part of the set.');

        $lines = $copy->lines()->get();

        $this->assertSame(
            ['FLOUR', 'LEMON WEDGES', 'BUTTER'],
            $lines->map(fn ($l) => $l->displayName())->all(),
            'Order is physical — labels peel off the roll in this order.'
        );

        $first = $lines->first();
        $this->assertSame(3, $first->copies);
        $this->assertSame('2.5', rtrim(rtrim((string) $first->quantity, '0'), '.'), 'Both sides are print sets, so quantities mean the same thing and carry.');
        $this->assertSame($this->uom->id, $first->uom_id);

        $custom = $lines[1];
        $this->assertSame('opened', $custom->label_type);
        $this->assertSame('freeze', $custom->storage_state);
    }

    public function test_importing_into_the_open_set_adds_only_what_is_new(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');

        $source = $this->sourceSet($this->otherOutlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id],
            ['labelable_type' => Ingredient::class, 'labelable_id' => $butter->id],
        ]);

        $mine = $this->sourceSet($this->outlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id],
        ], ['name' => 'Grill Station']);

        $this->managerScreen()
            ->call('editLines', $mine->id)
            ->call('openSetImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', $source->id)
            ->set('importSetTarget', 'current')
            ->call('importSet')
            ->assertHasNoErrors();

        $this->assertSame(
            2,
            $mine->lines()->count(),
            'Re-importing must top the set up, not double it.'
        );
    }

    public function test_a_line_whose_item_was_deleted_is_skipped_and_reported(): void
    {
        $flour = $this->ingredient('FLOUR');
        $gone  = $this->ingredient('DISCONTINUED');

        $source = $this->sourceSet($this->otherOutlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id],
            ['labelable_type' => Ingredient::class, 'labelable_id' => $gone->id],
        ]);

        $gone->delete();

        $html = $this->managerScreen()
            ->call('openSetImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', $source->id)
            ->call('importSet')
            ->assertHasNoErrors()
            ->html();

        $copy = LabelSet::where('outlet_id', $this->outlet->id)->firstOrFail();

        $this->assertSame(1, $copy->lines()->count());

        // Asserted on what the screen SAYS: a skipped line the person is never
        // told about is the same as one silently dropped.
        $this->assertStringContainsString('no longer exists', $html);
    }

    public function test_another_companys_outlet_is_rejected(): void
    {
        $rival       = Company::create([
            'name' => 'Rival Co', 'slug' => Str::slug('Rival Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $rivalOutlet = Outlet::create([
            'company_id' => $rival->id, 'name' => 'Rival Main', 'code' => 'RVL', 'is_active' => true,
        ]);
        $this->sourceSet($rivalOutlet, []);

        $this->managerScreen()
            ->call('openSetImport')
            ->set('importOutletId', $rivalOutlet->id)
            ->set('importSetId', LabelSet::withoutGlobalScopes()->firstOrFail()->id)
            ->set('importSetName', 'Stolen')
            ->call('importSet')
            ->assertHasErrors('importOutletId');

        $this->assertSame(0, LabelSet::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->count());
    }

    // ── Staff PWA ─────────────────────────────────────────────────────────

    /**
     * A PIN session, not a guard — there is no actingAs() for this. Signed in
     * via email because that is the representative case: on a real company
     * most staff have an email and no PIN at all.
     */
    private function asStaff(Employee $employee): void
    {
        session(['subdomain_company_id' => $employee->company_id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($employee, 'email');
    }

    private function employee(Outlet $outlet): Employee
    {
        return Employee::create([
            'company_id' => $outlet->company_id,
            'outlet_id'  => $outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);
    }

    public function test_staff_can_import_a_set_from_another_outlet(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $source = $this->sourceSet($this->otherOutlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id],
        ]);

        $this->asStaff($this->employee($this->outlet));

        Livewire::test(StaffSets::class)
            ->call('openImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', $source->id)
            ->call('importSet')
            ->assertHasNoErrors();

        $copy = LabelSet::withoutGlobalScopes()
            ->where('outlet_id', $this->outlet->id)
            ->firstOrFail();

        $this->assertSame('Chiller 1', $copy->name);
        $this->assertSame(1, $copy->lines()->count());
        $this->assertSame(
            1,
            $source->fresh()->lines()->count(),
            'A copy must not touch the outlet it came from.'
        );
    }

    public function test_importing_twice_tops_up_the_copy_rather_than_duplicating_it(): void
    {
        $flour  = $this->ingredient('FLOUR');
        $butter = $this->ingredient('BUTTER');

        $source = $this->sourceSet($this->otherOutlet, [
            ['labelable_type' => Ingredient::class, 'labelable_id' => $flour->id],
        ]);

        $this->asStaff($this->employee($this->outlet));

        $screen = Livewire::test(StaffSets::class)
            ->call('openImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', $source->id)
            ->call('importSet');

        // The source grows, as sets do.
        LabelSetLine::create([
            'label_set_id'  => $source->id,
            'sort_order'    => 1,
            'labelable_type' => Ingredient::class,
            'labelable_id'  => $butter->id,
            'label_type'    => 'prep',
            'storage_state' => 'chill',
            'copies'        => 1,
        ]);

        $screen->call('openImport')
            ->set('importOutletId', $this->otherOutlet->id)
            ->set('importSetId', $source->id)
            ->call('importSet')
            ->assertHasNoErrors();

        $copies = LabelSet::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->get();

        $this->assertCount(1, $copies, 'A double tap must not mean a second "Chiller 1".');
        $this->assertSame(2, $copies->first()->lines()->count());
    }

    public function test_staff_cannot_import_from_another_company(): void
    {
        $rival       = Company::create([
            'name' => 'Rival Co', 'slug' => Str::slug('Rival Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $rivalOutlet = Outlet::create([
            'company_id' => $rival->id, 'name' => 'Rival Main', 'code' => 'RVL', 'is_active' => true,
        ]);
        $rivalSet = $this->sourceSet($rivalOutlet, []);

        $this->asStaff($this->employee($this->outlet));

        Livewire::test(StaffSets::class)
            ->call('openImport')
            ->set('importOutletId', $rivalOutlet->id)
            ->set('importSetId', $rivalSet->id)
            ->call('importSet')
            ->assertHasErrors('importOutletId');

        $this->assertSame(
            0,
            LabelSet::withoutGlobalScopes()->where('outlet_id', $this->outlet->id)->count()
        );
    }
}
