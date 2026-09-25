<?php

namespace Tests\Feature;

use App\Livewire\Inventory\TransferForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AssetOnHandService;
use App\Services\CostSummaryService;
use App\Services\TransferConsolidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Loading an Asset Count form onto an outlet-to-outlet transfer.
 *
 * One branch lending or handing crockery to another used to be two unrelated
 * documents in the asset module. The transfer is now that document: a count
 * sheet loads onto it as asset lines, sending takes them off the source
 * outlet's register, receiving puts them on the destination's, and
 * cancelling or deleting takes both entries away. Food cost never sees them.
 */
class OutletTransferAssetTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $from;
    private Outlet $to;
    private User $user;
    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Transfer Asset Co', 'slug' => Str::slug('Transfer Asset Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->from = Outlet::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->to   = Outlet::create(['company_id' => $this->company->id, 'name' => 'Branch', 'code' => 'BR', 'is_active' => true]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->from->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->from->id, $this->to->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['inventory.view', 'inventory.transfers.record'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->pcs = UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'pcs', 'type' => 'count', 'base_unit_factor' => 1]);

        $this->actingAs($this->user);
    }

    private function grantAssetAccess(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('assets.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function asset(string $name, float $cost): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id, 'name' => $name,
            'uom_id' => $this->pcs->id, 'unit_cost' => $cost, 'is_active' => true,
        ]);
    }

    private function countSheet(string $name, array $assets): FormTemplate
    {
        $t = FormTemplate::create([
            'company_id' => $this->company->id, 'name' => $name,
            'form_type' => 'asset_count', 'is_active' => true,
        ]);

        foreach ($assets as $i => [$asset, $qty]) {
            FormTemplateLine::create([
                'form_template_id' => $t->id, 'item_type' => 'asset',
                'asset_id' => $asset->id, 'default_quantity' => $qty, 'sort_order' => $i,
            ]);
        }

        return $t;
    }

    private function form()
    {
        return Livewire::test(TransferForm::class)
            ->set('from_outlet_id', (string) $this->from->id)
            ->set('to_outlet_id', (string) $this->to->id);
    }

    /** A saved draft carrying 24 plates at RM 12.50, reopened for send/receive. */
    private function savedTransferOfPlates(Asset $plate)
    {
        $this->grantAssetAccess();
        $sheet = $this->countSheet('Crockery count', [[$plate, 24]]);

        $this->form()->set('selectedTemplateId', (string) $sheet->id)->call('save')->assertHasNoErrors();

        return Livewire::test(TransferForm::class, ['id' => OutletTransfer::firstOrFail()->id]);
    }

    private function onHand(Asset $asset, Outlet $outlet): float
    {
        return app(AssetOnHandService::class)->quantity($asset->id, $outlet->id, $this->company->id);
    }

    public function test_an_asset_count_form_loads_its_assets_at_the_sheet_quantity(): void
    {
        $this->grantAssetAccess();

        $plate = $this->asset('DINNER PLATE', 12.5);
        $bowl  = $this->asset('SOUP BOWL', 9);
        $sheet = $this->countSheet('Crockery count', [[$plate, 24], [$bowl, 12]]);

        $screen = $this->form()
            ->assertSee('Crockery count — Asset Count, 2 items')
            ->set('selectedTemplateId', (string) $sheet->id);

        $lines = $screen->get('lines');

        $this->assertCount(2, $lines);
        $this->assertSame('asset', $lines[0]['item_type']);
        $this->assertSame($plate->id, (int) $lines[0]['asset_id']);
        $this->assertNull($lines[0]['ingredient_id'], 'An asset line never moves stock on hand.');
        $this->assertSame(24.0, (float) $lines[0]['quantity']);
        $this->assertSame(12.5, (float) $lines[0]['unit_cost'], 'Cost comes off the asset.');
        $this->assertSame(300.0, (float) $lines[0]['total_cost']);
        $this->assertSame('pcs', $lines[0]['uom_abbr']);
        $this->assertSame('', $screen->get('selectedTemplateId'), 'The picker resets, so the same sheet can be loaded again.');

        // Loading again adds nothing.
        $screen->set('selectedTemplateId', (string) $sheet->id);
        $this->assertCount(2, $screen->get('lines'));
    }

    public function test_asset_count_forms_need_asset_access(): void
    {
        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        $this->form()
            ->assertDontSee('Crockery count')
            ->set('selectedTemplateId', (string) $sheet->id)
            ->assertSet('lines', []);
    }

    /** The cost is locked: a crafted request cannot re-price the plates. */
    public function test_an_asset_line_saves_at_the_asset_cost_whatever_the_row_says(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);

        $this->savedTransferOfPlates($plate)
            ->assertSee('ASSET')
            ->set('lines.0.unit_cost', '0.01')
            ->call('save')
            ->assertHasNoErrors();

        $line = OutletTransfer::with('lines')->firstOrFail()->lines->sole();

        $this->assertSame($plate->id, (int) $line->asset_id);
        $this->assertNull($line->ingredient_id);
        $this->assertSame(12.5, (float) $line->unit_cost);
        $this->assertSame('DINNER PLATE', $line->item_name);
    }

    public function test_sending_takes_the_assets_off_the_source_and_receiving_puts_them_on_the_destination(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);
        $screen = $this->savedTransferOfPlates($plate);

        $screen->call('send');

        $disposal = AssetMovement::where('movement_type', AssetMovement::TYPE_DISPOSAL)->sole();
        $this->assertSame($this->from->id, (int) $disposal->outlet_id);
        $this->assertSame('Transferred to another outlet', $disposal->reasonLabel());
        $this->assertSame(-24.0, $this->onHand($plate, $this->from), 'In transit, the plates have left the source.');
        $this->assertSame(0.0, $this->onHand($plate, $this->to), 'In transit, they have not reached the destination.');

        $screen->call('receive');

        $receipt = AssetMovement::where('movement_type', AssetMovement::TYPE_RECEIPT)->sole();
        $this->assertSame($this->to->id, (int) $receipt->outlet_id);
        $this->assertSame(OutletTransfer::firstOrFail()->transfer_number, $receipt->reference_number);
        $this->assertSame(24.0, $this->onHand($plate, $this->to));
        $this->assertSame(1, AssetMovement::where('movement_type', AssetMovement::TYPE_DISPOSAL)->count(), 'Receiving does not dispose twice.');
    }

    public function test_cancelling_in_transit_puts_the_assets_back(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);

        $this->savedTransferOfPlates($plate)->call('send')->call('cancel');

        $this->assertSame(0, AssetMovement::count());
        $this->assertSame(0.0, $this->onHand($plate, $this->from));
    }

    public function test_deleting_a_received_transfer_takes_both_register_entries_with_it(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);

        $this->savedTransferOfPlates($plate)->call('send')->call('receive');
        $this->assertSame(2, AssetMovement::count());

        OutletTransfer::firstOrFail()->delete();

        $this->assertSame(0, AssetMovement::count());
        $this->assertSame(0.0, $this->onHand($plate, $this->to));
    }

    public function test_food_cost_does_not_count_the_plates(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->pcs->id, 'recipe_uom_id' => $this->pcs->id,
            'is_active' => true, 'purchase_price' => 10, 'pack_size' => 1, 'yield_percent' => 100,
        ]);

        $screen = $this->savedTransferOfPlates($plate);
        $screen->call('receive');   // still a draft: receive only works in transit
        $screen->call('send')->call('receive');

        // Add a food line on a second transfer for comparison.
        $this->form()->call('addIngredient', $flour->id)->set('lines.0.quantity', '3')->call('save');
        Livewire::test(TransferForm::class, ['id' => OutletTransfer::latest('id')->firstOrFail()->id])
            ->call('send')->call('receive');

        $food = OutletTransfer::latest('id')->firstOrFail()->lines->sole();
        $expected = round((float) $food->quantity * (float) $food->unit_cost, 2);

        $summary = app(CostSummaryService::class)->generate(now()->format('Y-m'), $this->to->id);

        $this->assertEqualsWithDelta($expected, $summary['totals']['transfer_in'], 0.01, 'Only the flour is food cost.');
    }

    public function test_the_detail_report_files_assets_under_their_own_heading(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);
        $this->savedTransferOfPlates($plate);

        $report = app(TransferConsolidator::class)->consolidate(OutletTransfer::with('lines')->get());
        $groups = collect($report['groups'])->keyBy('name');

        $this->assertSame('DINNER PLATE', $groups['Assets']['items'][0]['name']);
        $this->assertEquals(300.0, $groups['Assets']['value']);
    }
}
