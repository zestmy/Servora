<?php

namespace Tests\Feature;

use App\Livewire\Sales\PosSync;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PosAgent;
use App\Models\PosSalesBatch;
use App\Models\SalesCategory;
use App\Models\User;
use App\Models\ZeoniqDepartmentMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The POS Sync screen's own guarantees: it opens only behind sales.import,
 * pairing shows a code (never a token), and the needs_mapping flow saves to
 * the same zeoniq_department_mappings rows the Excel import screen writes.
 */
class PosSyncScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['error' => 'faked'], 500)]);

        $this->company = Company::create([
            'name' => 'Sync UI Co', 'slug' => Str::slug('Sync UI Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('sales.import', 'web');
        $this->user->givePermissionTo('sales.import');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_the_page_requires_sales_import(): void
    {
        $this->actingAs($this->user)->get('/sales/pos-sync')->assertOk();

        $other = User::factory()->create(['company_id' => $this->company->id]);
        $other->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($other)->get('/sales/pos-sync')->assertForbidden();
    }

    public function test_creating_an_agent_shows_a_pairing_code_and_never_a_token(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(PosSync::class)
            ->call('openCreate')
            ->set('name', 'Front counter till')
            ->set('outlet_id', $this->outlet->id)
            ->call('save');

        $agent = PosAgent::withoutGlobalScopes()->sole();
        $this->assertSame('Front counter till', $agent->name);
        $this->assertNotNull($agent->pairing_code);
        // The code is on screen; no token exists yet at all.
        $component->assertSee($agent->pairing_code);
        $this->assertNull($agent->token_hash);
    }

    public function test_mapping_flow_saves_shared_mappings_and_reapplies(): void
    {
        $food = SalesCategory::create([
            'company_id' => $this->company->id, 'name' => 'Food', 'type' => 'food',
        ]);

        $agent = PosAgent::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Till',
        ]);

        $batch = PosSalesBatch::create([
            'company_id'      => $this->company->id,
            'pos_agent_id'    => $agent->id,
            'outlet_id'       => $this->outlet->id,
            'source_filename' => 'DailySummary.xlsx',
            'sha256'          => str_repeat('a', 64),
            'status'          => PosSalesBatch::STATUS_NEEDS_MAPPING,
            'parsed_summary'  => [
                'unmapped_departments' => ['Hot Food'],
                'suggestions'          => [[
                    'zeoniq_department' => 'Hot Food',
                    'suggested_category_id' => $food->id,
                    'category_name' => 'Food',
                    'confidence' => 'high',
                ]],
            ],
        ]);

        Livewire::actingAs($this->user)
            ->test(PosSync::class)
            ->call('openMapping', $batch->id)
            // The AI suggestion arrives pre-selected.
            ->assertSet('mappingSelections.Hot Food', $food->id)
            ->call('saveMappings')
            ->assertHasNoErrors();

        // The mapping row both import paths read now exists.
        $this->assertDatabaseHas('zeoniq_department_mappings', [
            'company_id'             => $this->company->id,
            'zeoniq_department_name' => 'Hot Food',
            'sales_category_id'      => $food->id,
        ]);

        // Re-apply ran; with no stored file it lands in failed with a
        // readable error — the point here is the flow moved, not the parse.
        $this->assertNotSame(PosSalesBatch::STATUS_NEEDS_MAPPING, $batch->refresh()->status);
    }

    public function test_mapping_save_refuses_an_unfilled_department(): void
    {
        $agent = PosAgent::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'Till',
        ]);

        $batch = PosSalesBatch::create([
            'company_id'      => $this->company->id,
            'pos_agent_id'    => $agent->id,
            'outlet_id'       => $this->outlet->id,
            'source_filename' => 'DailySummary.xlsx',
            'sha256'          => str_repeat('b', 64),
            'status'          => PosSalesBatch::STATUS_NEEDS_MAPPING,
            'parsed_summary'  => ['unmapped_departments' => ['Mystery']],
        ]);

        Livewire::actingAs($this->user)
            ->test(PosSync::class)
            ->call('openMapping', $batch->id)
            ->call('saveMappings')
            ->assertHasErrors('mapping');

        $this->assertSame(0, ZeoniqDepartmentMapping::withoutGlobalScopes()->count());
        $this->assertSame(PosSalesBatch::STATUS_NEEDS_MAPPING, $batch->refresh()->status);
    }
}
