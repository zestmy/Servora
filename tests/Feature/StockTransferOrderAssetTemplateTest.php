<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\Index;
use App\Livewire\Purchasing\StockTransferForm;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\CentralPurchasingUnit;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\ProcurementInvoice;
use App\Models\StockTransferOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AssetOnHandService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Loading an Asset Count form onto a stock transfer order.
 *
 * The central purchasing unit restocks an outlet's crockery and smallwares
 * against the outlet's count sheet, so the sheet loads onto the transfer as
 * asset lines at the sheet's quantity. Receiving the transfer puts those
 * assets into the outlet's register — the same receipt somebody would
 * otherwise key by hand — and touches nothing else.
 */
class StockTransferOrderAssetTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;
    private CentralPurchasingUnit $cpu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'STO Asset Co', 'slug' => Str::slug('STO Asset Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);
        $this->cpu = CentralPurchasingUnit::create([
            'company_id' => $this->company->id, 'name' => 'HQ Purchasing', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['purchasing.view', 'purchasing.transfers.create'])
            ->map(fn ($p) => Permission::findOrCreate($p, 'web'))->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->user);
        session(['active_outlet_id' => $this->outlet->id]);
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
            'uom_id' => $this->piece->id, 'unit_cost' => $cost, 'is_active' => true,
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
        return Livewire::test(StockTransferForm::class)
            ->set('cpu_id', $this->cpu->id)
            ->set('to_outlet_id', $this->outlet->id);
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
        $this->assertSame($plate->id, (int) $lines[0]['asset_id']);
        $this->assertNull($lines[0]['ingredient_id'], 'An asset line carries no ingredient.');
        $this->assertSame(24.0, (float) $lines[0]['quantity'], 'The count sheet quantity is how many to send.');
        $this->assertSame(12.5, (float) $lines[0]['unit_cost'], 'Cost comes off the asset.');
        $this->assertSame($this->piece->id, (int) $lines[0]['uom_id']);
        $this->assertSame(12.0, (float) $lines[1]['quantity']);
        $this->assertSame('', $screen->get('selectedTemplateId'), 'The picker resets, so the same sheet can be loaded again.');
    }

    public function test_loading_a_sheet_twice_does_not_double_the_transfer(): void
    {
        $this->grantAssetAccess();

        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        $screen = $this->form()
            ->set('selectedTemplateId', (string) $sheet->id)
            ->set('selectedTemplateId', (string) $sheet->id);

        $this->assertCount(1, $screen->get('lines'));
    }

    /** The template is not a back door into the asset register. */
    public function test_asset_count_forms_need_asset_access(): void
    {
        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        $this->form()
            ->assertDontSee('Crockery count')
            ->set('selectedTemplateId', (string) $sheet->id)
            ->assertSet('lines', []);
    }

    public function test_the_loaded_assets_save_beside_ingredients_and_reach_the_invoice(): void
    {
        $this->grantAssetAccess();

        $plate = $this->asset('DINNER PLATE', 12.5);
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id,
            'is_active' => true, 'purchase_price' => 10,
        ]);
        $sheet = $this->countSheet('Crockery count', [[$plate, 24]]);

        $this->form()
            ->call('addIngredient', $flour->id)
            ->set('selectedTemplateId', (string) $sheet->id)
            ->set('is_chargeable', true)
            ->call('save', 'send')
            ->assertHasNoErrors();

        $sto   = StockTransferOrder::with('lines')->firstOrFail();
        $asset = $sto->lines->firstWhere('asset_id', $plate->id);

        $this->assertCount(2, $sto->lines);
        $this->assertNotNull($asset);
        $this->assertNull($asset->ingredient_id);
        $this->assertSame(24.0, (float) $asset->quantity);
        $this->assertSame('DINNER PLATE', $asset->displayName());

        $invoiceLine = ProcurementInvoice::where('stock_transfer_order_id', $sto->id)->firstOrFail()
            ->lines()->where('asset_id', $plate->id)->first();

        $this->assertNotNull($invoiceLine, 'A chargeable transfer invoices its assets too.');
        $this->assertSame(300.0, (float) $invoiceLine->total_price);
    }

    public function test_a_line_naming_nothing_is_refused(): void
    {
        $this->form()
            ->set('lines', [[
                'ingredient_id' => null, 'asset_id' => null, 'ingredient_name' => '—',
                'quantity' => '1', 'uom_id' => $this->piece->id, 'unit_cost' => '0',
            ]])
            ->call('save')
            ->assertHasErrors('lines.0.ingredient_id');

        $this->assertSame(0, StockTransferOrder::count());
    }

    /**
     * Receiving puts the assets in the outlet's register, once, valued at the
     * asset's own cost when the transfer was free.
     */
    public function test_receiving_the_transfer_adds_its_assets_to_the_register_once(): void
    {
        $plate = $this->asset('DINNER PLATE', 12.5);

        $sto = StockTransferService::create([
            'company_id' => $this->company->id, 'cpu_id' => $this->cpu->id,
            'to_outlet_id' => $this->outlet->id, 'transfer_date' => now()->toDateString(),
            'status' => 'sent', 'is_chargeable' => false,
        ], [[
            'ingredient_id' => null, 'asset_id' => $plate->id,
            'quantity' => 24, 'uom_id' => $this->piece->id, 'unit_cost' => 12.5,
        ]]);

        Livewire::test(Index::class)->call('receiveSto', $sto->id);

        $this->assertSame('received', $sto->fresh()->status);

        $receipt = AssetMovement::where('stock_transfer_order_id', $sto->id)->with('lines')->sole();

        $this->assertSame(AssetMovement::TYPE_RECEIPT, $receipt->movement_type);
        $this->assertSame($this->outlet->id, (int) $receipt->outlet_id);
        $this->assertSame($sto->sto_number, $receipt->reference_number);
        $this->assertSame(12.5, (float) $receipt->lines->sole()->unit_cost, 'A free transfer is still valued at the asset\'s cost.');
        $this->assertSame(24.0, app(AssetOnHandService::class)->quantity($plate->id, $this->outlet->id, $this->company->id));

        // A second receive — the button is gone, but the service is keyed anyway.
        StockTransferService::receiveAssets($sto->fresh());

        $this->assertSame(1, AssetMovement::where('stock_transfer_order_id', $sto->id)->count());
        $this->assertSame(24.0, app(AssetOnHandService::class)->quantity($plate->id, $this->outlet->id, $this->company->id));
    }

    public function test_receiving_a_food_only_transfer_writes_no_asset_receipt(): void
    {
        $flour = Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'FLOUR',
            'base_uom_id' => $this->piece->id, 'recipe_uom_id' => $this->piece->id, 'is_active' => true,
        ]);

        $sto = StockTransferService::create([
            'company_id' => $this->company->id, 'cpu_id' => $this->cpu->id,
            'to_outlet_id' => $this->outlet->id, 'transfer_date' => now()->toDateString(),
            'status' => 'sent', 'is_chargeable' => false,
        ], [[
            'ingredient_id' => $flour->id, 'quantity' => 5, 'uom_id' => $this->piece->id, 'unit_cost' => 10,
        ]]);

        Livewire::test(Index::class)->call('receiveSto', $sto->id);

        $this->assertSame('received', $sto->fresh()->status);
        $this->assertSame(0, AssetMovement::count());
    }
}
