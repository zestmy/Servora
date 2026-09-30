<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StockTakeForm;
use App\Models\Company;
use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\IngredientUomConversion;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A stock take line is counted as full packs (purchase UOM) plus loose stock
 * (recipe UOM), side by side: "2 batches + 3 pcs".
 *
 * What is stored as actual_quantity is still the total in the recipe UOM,
 * because stock on hand, variance and the balance reports read it without
 * looking at a unit. The split is kept only so a draft reopens as typed.
 */
class StockTakePackAndLooseCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Ingredient $dough;
    private Department $department;
    private UnitOfMeasure $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Dough Co', 'slug' => Str::slug('Dough Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.stock_takes.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $batch       = UnitOfMeasure::create(['name' => 'Batch', 'abbreviation' => 'batch', 'type' => 'count']);
        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pcs',   'type' => 'count']);

        // Bought by the batch at RM28.45, used by the piece: 1 batch = 10 pcs.
        $this->dough = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Pizza Dough',
            'base_uom_id' => $batch->id, 'recipe_uom_id' => $this->piece->id,
            'current_cost' => 28.45, 'is_active' => true,
        ]);
        IngredientUomConversion::create([
            'ingredient_id' => $this->dough->id,
            'from_uom_id' => $batch->id, 'to_uom_id' => $this->piece->id, 'factor' => 10,
        ]);

        $this->department = Department::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true]);

        $this->actingAs($this->user);
    }

    private function loaded()
    {
        $template = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => 'Weekly Count',
            'form_type' => 'stock_take', 'is_active' => true,
        ]);
        FormTemplateLine::create([
            'form_template_id' => $template->id, 'item_type' => 'ingredient',
            'ingredient_id' => $this->dough->id, 'sort_order' => 1,
        ]);

        return Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->set('selectedTemplateId', (string) $template->id)
            ->call('loadTemplate')
            ->set('department_id', $this->department->id);
    }

    public function test_system_qty_and_variance_are_hidden_by_default(): void
    {
        Livewire::actingAs($this->user)->test(StockTakeForm::class)->assertSet('hideSystemQty', true);
    }

    public function test_both_units_are_offered_side_by_side(): void
    {
        $line = $this->loaded()
            ->assertSee('Purchase UOM')
            ->assertSee('Recipe UOM')
            ->get('lines')[0];

        $this->assertSame('batch', $line['pack_uom_abbr']);
        $this->assertSame('pcs', $line['uom_abbr']);
    }

    public function test_packs_plus_loose_is_stored_as_one_recipe_uom_total(): void
    {
        $component = $this->loaded()
            ->set('lines.0.pack_quantity', '2')
            ->set('lines.0.actual_quantity', '3');

        $this->assertEquals(23.0, $component->get('lines')[0]['counted_quantity'], '2 batches + 3 pcs = 23 pcs');

        $component->call('save', 'save');

        $stockTake = StockTake::find($component->get('recordId'));
        $stored    = $stockTake->lines()->first();

        $this->assertSame($this->piece->id, (int) $stored->uom_id, 'Lines stay in the recipe UOM.');
        $this->assertEquals(23.0, (float) $stored->actual_quantity);
        $this->assertEquals(2.0, (float) $stored->pack_quantity);
        $this->assertEquals(3.0, (float) $stored->loose_quantity);
        $this->assertEquals(65.44, round((float) $stockTake->total_stock_cost, 2), '23 pcs x RM2.845');
    }

    public function test_a_reopened_draft_shows_the_count_as_it_was_typed(): void
    {
        $id = $this->loaded()
            ->set('lines.0.pack_quantity', '2')
            ->set('lines.0.actual_quantity', '3')
            ->call('save', 'save')
            ->get('recordId');

        $line = Livewire::actingAs($this->user)->test(StockTakeForm::class, ['id' => $id])->get('lines')[0];

        $this->assertSame('2', $line['pack_quantity']);
        $this->assertSame('3', $line['actual_quantity']);
        $this->assertEquals(23.0, $line['counted_quantity']);
    }

    public function test_loose_only_counts_are_stored_exactly_as_before(): void
    {
        $stored = StockTake::find(
            $this->loaded()->set('lines.0.actual_quantity', '7')->call('save', 'save')->get('recordId')
        )->lines()->first();

        $this->assertEquals(7.0, (float) $stored->actual_quantity);
        $this->assertNull($stored->pack_quantity);
        $this->assertNull($stored->loose_quantity);
    }

    /** No conversion between the two units: one column, never a 1:1 guess. */
    public function test_an_item_without_a_conversion_has_no_purchase_column(): void
    {
        IngredientUomConversion::where('ingredient_id', $this->dough->id)->delete();

        $component = $this->loaded()
            ->set('lines.0.pack_quantity', '2')   // ignored: no column for it
            ->set('lines.0.actual_quantity', '7');

        $this->assertSame('', $component->get('lines')[0]['pack_uom_abbr']);

        $stored = StockTake::find($component->call('save', 'save')->get('recordId'))->lines()->first();
        $this->assertEquals(7.0, (float) $stored->actual_quantity);
    }

    /** The total is worked out on the server; a total sent from the browser is ignored. */
    public function test_a_submitted_total_is_not_trusted(): void
    {
        $component = $this->loaded()
            ->set('lines.0.pack_quantity', '1')
            ->set('lines.0.actual_quantity', '0')
            ->set('lines.0.counted_quantity', 9999)
            ->call('save', 'save');

        $this->assertEquals(10.0, (float) StockTake::find($component->get('recordId'))->lines()->first()->actual_quantity);
    }

    public function test_the_pack_factor_cannot_be_set_from_the_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        $this->loaded()->set('packFactors', [$this->dough->id => 1000]);
    }

    /**
     * The consolidated inventory (by department) reads actual_quantity and
     * uom_id, which a packs + loose count still stores as one recipe-UOM total.
     * Two sheets in the same department, one split and one loose-only, must
     * download and add up in pieces: 2 batches + 3 pcs + 4 pcs = 27 pcs.
     */
    public function test_the_consolidated_sheet_by_department_adds_pack_counts_correctly(): void
    {
        $this->loaded()
            ->set('lines.0.pack_quantity', '2')
            ->set('lines.0.actual_quantity', '3')
            ->call('save', 'complete');
        $this->loaded()
            ->set('lines.0.actual_quantity', '4')
            ->call('save', 'complete');

        $query = ['from' => today()->subDay()->toDateString(), 'to' => today()->addDay()->toDateString(), 'department' => $this->department->id];

        $this->get(route('inventory.stock-takes.consolidated', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $response = $this->get(route('inventory.stock-takes.consolidated-excel', $query))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'cst') . '.xlsx';
        file_put_contents($path, $response->streamedContent());
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, true);
        @unlink($path);

        $dough = collect($rows)->first(fn ($r) => str_contains((string) ($r['A'] ?? ''), 'PIZZA DOUGH'));
        $this->assertNotNull($dough, 'The item should appear once in the consolidated sheet.');
        $this->assertContains(27.0, array_map('floatval', array_filter($dough, 'is_numeric')), '2 batches + 3 pcs + 4 pcs = 27 pcs');
    }

    public function test_completing_keeps_a_line_counted_only_in_packs(): void
    {
        $this->loaded()
            ->set('lines.0.pack_quantity', '3')
            ->set('lines.0.actual_quantity', '0')
            ->call('save', 'complete');

        $stockTake = StockTake::where('company_id', $this->company->id)->latest('id')->first();

        $this->assertSame('completed', $stockTake->status);
        $this->assertEquals(30.0, (float) $stockTake->lines()->first()->actual_quantity);
    }
}
