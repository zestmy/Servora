<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\OrderForm;
use App\Models\Asset;
use App\Models\Company;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Loading an Asset Count form onto a purchase order.
 *
 * A count sheet is the list of what an outlet is meant to hold, which is
 * exactly what a replacement order is written against. The "Load Template"
 * picker on the order used to offer Purchase Order forms only; it now offers
 * count sheets too, and their assets come across the way a converted
 * request's do — cost off the asset, its own unit — at the sheet's quantity.
 */
class PurchaseOrderTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private UnitOfMeasure $piece;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PO Template Co', 'slug' => Str::slug('PO Template Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $this->piece = UnitOfMeasure::create(['name' => 'Piece', 'abbreviation' => 'pc', 'type' => 'count']);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(collect(['purchasing.view', 'purchasing.orders.create', 'purchasing.orders.edit'])
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

    public function test_an_asset_count_form_loads_its_assets_at_the_sheet_quantity(): void
    {
        $this->grantAssetAccess();

        $plate = $this->asset('DINNER PLATE', 12.5);
        $bowl  = $this->asset('SOUP BOWL', 9);
        $sheet = $this->countSheet('Crockery count', [[$plate, 24], [$bowl, 12]]);

        $screen = Livewire::test(OrderForm::class)
            ->assertSee('Crockery count — Asset Count')
            ->set('selectedTemplateId', (string) $sheet->id);

        $lines = $screen->get('lines');

        $this->assertCount(2, $lines);
        $this->assertSame($plate->id, (int) $lines[0]['asset_id']);
        $this->assertNull($lines[0]['ingredient_id'], 'An asset line carries no ingredient.');
        $this->assertSame(24.0, (float) $lines[0]['quantity'], 'The count sheet quantity is how many to order.');
        $this->assertSame(12.5, (float) $lines[0]['unit_cost'], 'Cost comes off the asset, not a supplier price list.');
        $this->assertSame($this->piece->id, (int) $lines[0]['uom_id']);
        $this->assertSame(12.0, (float) $lines[1]['quantity']);
        $this->assertSame('', $screen->get('selectedTemplateId'), 'The picker resets, so the same sheet can be loaded again.');
    }

    public function test_the_loaded_assets_save_as_asset_lines(): void
    {
        $this->grantAssetAccess();

        $supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen Kit Sdn Bhd', 'is_active' => true,
        ]);
        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        Livewire::test(OrderForm::class)
            ->set('supplier_id', $supplier->id)
            ->set('selectedTemplateId', (string) $sheet->id)
            ->call('save')
            ->assertHasNoErrors();

        $line = PurchaseOrder::with('lines')->firstOrFail()->lines->sole();

        $this->assertTrue($line->isAssetItem());
        $this->assertSame(24.0, (float) $line->quantity);
        $this->assertSame(12.5, (float) $line->unit_cost);
    }

    public function test_loading_a_sheet_twice_does_not_double_the_order(): void
    {
        $this->grantAssetAccess();

        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        $screen = Livewire::test(OrderForm::class)
            ->set('selectedTemplateId', (string) $sheet->id)
            ->set('selectedTemplateId', (string) $sheet->id);

        $this->assertCount(1, $screen->get('lines'));
    }

    /**
     * The template is not a back door into the asset register: somebody who
     * may not request an asset is not offered a count sheet, and picking one
     * anyway loads nothing.
     */
    public function test_asset_count_forms_need_asset_access(): void
    {
        $sheet = $this->countSheet('Crockery count', [[$this->asset('DINNER PLATE', 12.5), 24]]);

        Livewire::test(OrderForm::class)
            ->assertDontSee('Crockery count')
            ->set('selectedTemplateId', (string) $sheet->id)
            ->assertSet('lines', []);
    }
}
