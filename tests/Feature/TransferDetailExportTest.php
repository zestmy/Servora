<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\OutletTransferLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TransferConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every item moved between outlets in a range, merged into one report.
 *
 * The Transfer Summary export answers "which outlet sent the most value".
 * This is the detail behind that number — the same relationship
 * WastageDetailExportTest pins for wastage, applied to stock moved instead
 * of stock thrown out. A transfer line only ever names an ingredient and
 * carries no stored cost of its own, so the value is quantity x unit_cost.
 */
class TransferDetailExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet1;
    private Outlet $outlet2;
    private User $user;
    private UnitOfMeasure $kg;
    private UnitOfMeasure $g;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Transfer Detail Co', 'slug' => Str::slug('Transfer Detail Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet1 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['company_id' => $this->company->id, 'name' => 'Second', 'code' => 'SEC', 'is_active' => true]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet1->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet1->id, $this->outlet2->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(Permission::findOrCreate('inventory.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $this->g  = UnitOfMeasure::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'weight', 'base_unit_factor' => 1]);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name, ?UnitOfMeasure $recipeUom = null, ?int $categoryId = null): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => ($recipeUom ?? $this->kg)->id,
            'ingredient_category_id' => $categoryId,
            'current_cost' => 10, 'is_active' => true,
        ]);
    }

    private function transfer(Outlet $from, Outlet $to, string $date = '2026-08-05', string $status = 'received'): OutletTransfer
    {
        return OutletTransfer::create([
            'company_id' => $this->company->id, 'from_outlet_id' => $from->id, 'to_outlet_id' => $to->id,
            'transfer_number' => 'T-' . uniqid(), 'status' => $status, 'transfer_date' => $date,
        ]);
    }

    private function line(OutletTransfer $transfer, Ingredient $ing, float $qty, float $unitCost, ?UnitOfMeasure $uom = null): void
    {
        OutletTransferLine::create([
            'outlet_transfer_id' => $transfer->id, 'ingredient_id' => $ing->id,
            'quantity' => $qty, 'uom_id' => ($uom ?? $this->kg)->id, 'unit_cost' => $unitCost,
        ]);
    }

    private function consolidate(): array
    {
        $transfers = OutletTransfer::with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent'])
            ->orderBy('id')->get();

        return app(TransferConsolidator::class)->consolidate($transfers);
    }

    private function itemNamed(array $report, string $name): ?array
    {
        foreach ($report['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if ($item['name'] === $name) {
                    return $item;
                }
            }
        }

        return null;
    }

    // ── The merge ────────────────────────────────────────────────────────

    public function test_one_item_moved_on_two_transfers_appears_once_with_both_quantities(): void
    {
        $flour = $this->ingredient('Flour');

        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 4, 10);
        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 6, 10);

        $report = $this->consolidate();
        $item   = $this->itemNamed($report, 'FLOUR');

        $this->assertSame(1, $report['itemCount']);
        $this->assertSame(10.0, $item['quantity']);
        $this->assertSame(100.0, $item['value'], 'Value is quantity x unit_cost — a transfer stores no total_cost of its own.');
        $this->assertSame(2, $item['lines']);
    }

    public function test_quantities_in_different_units_are_converted_before_they_are_added(): void
    {
        $flour = $this->ingredient('Flour', $this->kg);

        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 500, 0.01, $this->g);
        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 2, 10, $this->kg);

        $item = $this->itemNamed($this->consolidate(), 'FLOUR');

        $this->assertSame('kg', $item['uom_abbr']);
        $this->assertSame(2.5, $item['quantity']);
        $this->assertSame(25.0, $item['value']);
    }

    public function test_the_unit_cost_is_the_rate_the_value_implies(): void
    {
        $oil = $this->ingredient('Oil');

        $this->line($this->transfer($this->outlet1, $this->outlet2), $oil, 10, 5.00);
        $this->line($this->transfer($this->outlet1, $this->outlet2), $oil, 10, 7.00);

        $item = $this->itemNamed($this->consolidate(), 'OIL');

        $this->assertSame(20.0, $item['quantity']);
        $this->assertSame(120.0, $item['value']);
        $this->assertSame(6.0, $item['unit_cost']);
    }

    public function test_the_total_is_the_sum_of_every_group(): void
    {
        $dairy = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $dry   = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dry Goods']);

        $transfer = $this->transfer($this->outlet1, $this->outlet2);
        $this->line($transfer, $this->ingredient('Butter', null, $dairy->id), 2, 15);
        $this->line($transfer, $this->ingredient('Flour', null, $dry->id), 3, 10);

        $report = $this->consolidate();

        $this->assertSame(60.0, $report['total']);
        $this->assertCount(2, $report['groups']);
    }

    public function test_the_report_only_covers_the_range_asked_for(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->transfer($this->outlet1, $this->outlet2, '2026-08-10'), $flour, 5, 10);
        $this->line($this->transfer($this->outlet1, $this->outlet2, '2026-09-10'), $flour, 99, 10);

        $transfers = OutletTransfer::with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent'])
            ->whereBetween('transfer_date', ['2026-08-01', '2026-08-31'])->get();

        $report = app(TransferConsolidator::class)->consolidate($transfers);

        $this->assertSame(50.0, $report['total'], 'September must not be in an August file.');
    }

    // ── The files ────────────────────────────────────────────────────────

    public function test_the_pdf_downloads_for_the_chosen_range(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 4, 10);

        $response = $this->get(route('inventory.transfers.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Transfer-Details-2026-08-01-to-2026-08-31', $response->headers->get('content-disposition'));
    }

    public function test_the_excel_downloads(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 4, 10);

        $response = $this->get(route('inventory.transfers.detail-excel', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Transfer-Details-2026-08-01-to-2026-08-31.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_a_range_with_no_transfers_still_renders(): void
    {
        $this->get(route('inventory.transfers.detail', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
    }

    public function test_a_status_filter_narrows_the_report(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->transfer($this->outlet1, $this->outlet2, '2026-08-05', 'received'), $flour, 10, 5);
        $this->line($this->transfer($this->outlet1, $this->outlet2, '2026-08-06', 'draft'), $flour, 4, 5);

        $response = $this->get(route('inventory.transfers.detail', [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'status' => 'received',
        ]));

        $response->assertOk();
    }

    public function test_the_buttons_appear_on_the_transfers_tab(): void
    {
        $flour = $this->ingredient('Flour');
        $this->line($this->transfer($this->outlet1, $this->outlet2), $flour, 4, 10);

        $html = \Livewire\Livewire::actingAs($this->user)->test(\App\Livewire\Inventory\Index::class)
            ->set('tab', 'transfers')->call('setQuickRange', 'all_time')->html();

        $this->assertStringContainsString('transfers-details', $html);
        $this->assertStringContainsString('transfers-details.xlsx', $html);
    }
}
