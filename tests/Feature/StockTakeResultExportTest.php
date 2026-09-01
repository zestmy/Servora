<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Index as StockManagement;
use App\Models\Company;
use App\Models\Department;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Models\StockTakeLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A finished count, exported.
 *
 * The count sheet is the blank that goes onto the shelf. This is what comes
 * back: expected against counted, what the difference was worth, filed as a PDF
 * or handed over as a workbook.
 *
 * Only for a completed count. A draft is still being worked on, and filing one
 * as a result puts a figure nobody has stood behind into a folder — so the
 * route refuses it rather than trusting the screen to have hidden the button.
 */
class StockTakeResultExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private UnitOfMeasure $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Export Co', 'slug' => Str::slug('Export Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.stock_takes.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name, ?int $categoryId = null): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'ingredient_category_id' => $categoryId,
            'current_cost' => 10, 'is_active' => true,
        ]);
    }

    /** A count of two items: one 2 over, one 1 short. */
    private function completedCount(string $status = 'completed'): StockTake
    {
        $dairy = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $dept  = Department::create(['company_id' => $this->company->id, 'name' => 'Hot Kitchen']);

        $take = StockTake::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'department_id' => $dept->id, 'created_by' => $this->user->id,
            'status' => $status, 'method' => 'detailed',
            'stock_take_date' => '2026-08-31', 'reference_number' => 'ST-AUG',
            'total_stock_cost' => 0, 'total_variance_cost' => 0,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->ingredient('Butter', $dairy->id)->id,
            'uom_id' => $this->kg->id,
            'system_quantity' => 8, 'actual_quantity' => 10, 'variance_quantity' => 2,
            'unit_cost' => 15, 'variance_cost' => 30,
        ]);

        StockTakeLine::create([
            'stock_take_id' => $take->id, 'ingredient_id' => $this->ingredient('Cheese', $dairy->id)->id,
            'uom_id' => $this->kg->id,
            'system_quantity' => 5, 'actual_quantity' => 4, 'variance_quantity' => -1,
            'unit_cost' => 20, 'variance_cost' => -20,
        ]);

        return $take;
    }

    // ── PDF ──────────────────────────────────────────────────────────────

    public function test_a_completed_count_downloads_as_a_pdf(): void
    {
        $take = $this->completedCount();

        $response = $this->get(route('inventory.stock-takes.result', $take->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Stock-Take-ST-AUG.pdf', $response->headers->get('content-disposition'));
    }

    public function test_a_draft_has_no_result_to_export(): void
    {
        $draft = $this->completedCount('draft');

        // The list hides the buttons on a draft row; the route is checked again
        // because a URL is not a button.
        $this->get(route('inventory.stock-takes.result', $draft->id))->assertNotFound();
        $this->get(route('inventory.stock-takes.result-excel', $draft->id))->assertNotFound();
    }

    // ── Excel ────────────────────────────────────────────────────────────

    public function test_a_completed_count_downloads_as_a_workbook(): void
    {
        $take = $this->completedCount();

        $response = $this->get(route('inventory.stock-takes.result-excel', $take->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Stock-Take-ST-AUG.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_the_workbook_carries_the_counted_figures_and_live_formulas(): void
    {
        $take = $this->completedCount();

        $path = tempnam(sys_get_temp_dir(), 'st') . '.xlsx';
        file_put_contents($path, $this->get(route('inventory.stock-takes.result-excel', $take->id))->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        $cells = [];
        foreach ($sheet->toArray(null, false, false, true) as $rowNum => $row) {
            $cells[$rowNum] = $row;
        }

        $flat = collect($cells)->flatten()->filter()->implode('|');

        $this->assertStringContainsString('ST-AUG', $flat, 'The workbook should name the count.');
        // Ingredient names are uppercased on save.
        $this->assertStringContainsString('BUTTER', $flat);
        $this->assertStringContainsString('CHEESE', $flat);

        // Value, variance and the totals are formulas over the cells beside them,
        // so changing a rate in Excel re-costs the count rather than lying.
        $this->assertMatchesRegularExpression('/=E\d+\*G\d+/', $flat, 'Stock value should be a formula.');
        $this->assertMatchesRegularExpression('/=E\d+-D\d+/', $flat, 'Variance should be derived from the counts.');
        $this->assertMatchesRegularExpression('/=SUM\(/', $flat, 'The totals should be a SUM.');

        @unlink($path);
    }

    public function test_the_totals_add_only_the_item_rows(): void
    {
        $take = $this->completedCount();

        $path = tempnam(sys_get_temp_dir(), 'st') . '.xlsx';
        file_put_contents($path, $this->get(route('inventory.stock-takes.result-excel', $take->id))->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();

        // Find the TOTAL row and read its formula: it must sum the item rows
        // only, or the category subtotal above them is counted twice.
        $totalFormula = null;
        foreach ($sheet->toArray(null, false, false, true) as $row) {
            if (($row['A'] ?? null) === 'TOTAL') {
                $totalFormula = $row['H'];
            }
        }

        $this->assertNotNull($totalFormula, 'There should be a TOTAL row.');
        $this->assertStringNotContainsString('H:H', $totalFormula, 'A whole-column sum would double-count the subtotals.');
        $this->assertMatchesRegularExpression('/^=SUM\(H\d+:H\d+\)$/', $totalFormula);

        @unlink($path);
    }

    // ── The screen ───────────────────────────────────────────────────────

    public function test_the_buttons_appear_only_on_a_completed_row(): void
    {
        $completed = $this->completedCount();
        $draft     = $this->completedCount('draft');

        $html = Livewire::actingAs($this->user)->test(StockManagement::class)
            ->set('tab', 'stock-takes')
            ->call('setQuickRange', 'all_time')
            ->html();

        $this->assertStringContainsString(
            route('inventory.stock-takes.result', $completed->id),
            $html,
            'A completed count should offer its exports.'
        );
        $this->assertStringNotContainsString(
            route('inventory.stock-takes.result', $draft->id),
            $html,
            'A draft is still being counted; it has no result to file.'
        );
    }
}
