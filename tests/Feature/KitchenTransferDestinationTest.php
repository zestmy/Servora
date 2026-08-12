<?php

namespace Tests\Feature;

use App\Livewire\Inventory\TransferForm;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A central kitchen can pick somewhere to send stock to.
 *
 * REPORTED AS: Central Kitchen › New Transfer, the destination dropdown lists
 * nothing.
 *
 * Both selects were fed from ONE list — the outlets the user can access. A
 * kitchen user is attached only to the kitchen's own outlet, the source
 * defaults to exactly that outlet, and the destination select excludes the
 * source. One entry, minus itself, is an empty dropdown.
 *
 * The two ends ask different questions. SOURCE is what you may take stock out
 * of, so it stays scoped to what you can access. DESTINATION is where you may
 * send it, which is any active outlet in the company — a kitchen exists to
 * supply branches it is not itself attached to. That matches the rule already
 * used when opening an existing transfer, which admits anyone who can see
 * EITHER end.
 */
class KitchenTransferDestinationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $kitchenOutlet;
    private Outlet $klcc;
    private Outlet $ioi;
    private User $kitchenUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'CK Co', 'slug' => Str::slug('CK Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->kitchenOutlet = $this->outlet('CENTRAL KITCHEN', 'CK');
        $this->klcc          = $this->outlet('KLCC', 'KLCC');
        $this->ioi           = $this->outlet('IOI', 'IOI');

        CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK Cheras', 'code' => 'CKC',
            'outlet_id' => $this->kitchenOutlet->id, 'is_active' => true,
        ]);

        // Attached ONLY to the kitchen's outlet — the shape that broke.
        $this->kitchenUser = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $this->kitchenUser->companies()->syncWithoutDetaching([$this->company->id]);
        $this->kitchenUser->outlets()->sync([$this->kitchenOutlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['inventory.view', 'inventory.transfers.record'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->kitchenUser->givePermissionTo(['inventory.view', 'inventory.transfers.record']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /** The reported bug. */
    public function test_a_kitchen_user_has_somewhere_to_send_stock_to(): void
    {
        $c = Livewire::actingAs($this->kitchenUser)->test(TransferForm::class);

        $from = $c->get('from_outlet_id');
        $this->assertSame((string) $this->kitchenOutlet->id, $from,
            'The source should default to the kitchen, which is what emptied the other select.');

        // What the dropdown actually renders: the destinations, less the source.
        $selectable = collect($c->viewData('destinationOutlets'))
            ->reject(fn ($o) => (string) $o->id === $from)
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['IOI', 'KLCC'], $selectable,
            'A kitchen with nowhere to send stock cannot do its job.');
    }

    /** Taking stock OUT is still restricted to what you can access. */
    public function test_the_source_list_stays_scoped_to_accessible_outlets(): void
    {
        $c = Livewire::actingAs($this->kitchenUser)->test(TransferForm::class);

        $this->assertSame(['CENTRAL KITCHEN'],
            $c->viewData('sourceOutlets')->pluck('name')->all());
    }

    public function test_a_transfer_to_an_outlet_the_user_cannot_access_saves(): void
    {
        Livewire::actingAs($this->kitchenUser)->test(TransferForm::class)
            ->set('to_outlet_id', (string) $this->klcc->id)
            ->call('save')
            ->assertHasNoErrors('to_outlet_id');
    }

    // ── What must stay shut ───────────────────────────────────────────────

    /** `exists:outlets,id` alone would have accepted another tenant's outlet. */
    public function test_another_companys_outlet_cannot_be_a_destination(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $foreign = Outlet::withoutGlobalScopes()->create([
            'company_id' => $other->id, 'name' => 'FOREIGN', 'code' => 'FGN', 'is_active' => true,
        ]);

        Livewire::actingAs($this->kitchenUser)->test(TransferForm::class)
            ->set('to_outlet_id', (string) $foreign->id)
            ->call('save')
            ->assertHasErrors('to_outlet_id');
    }

    public function test_a_deleted_outlet_cannot_be_a_destination(): void
    {
        $gone = $this->outlet('CLOSED', 'CLS');
        $gone->delete();

        Livewire::actingAs($this->kitchenUser)->test(TransferForm::class)
            ->set('to_outlet_id', (string) $gone->id)
            ->call('save')
            ->assertHasErrors('to_outlet_id');
    }

    /** Source and destination must still differ. */
    public function test_a_transfer_to_the_same_outlet_is_refused(): void
    {
        Livewire::actingAs($this->kitchenUser)->test(TransferForm::class)
            ->set('to_outlet_id', (string) $this->kitchenOutlet->id)
            ->call('save')
            ->assertHasErrors('to_outlet_id');
    }

    /** An ordinary multi-outlet user is unaffected. */
    public function test_an_ordinary_user_still_sees_their_own_outlets_as_sources(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->klcc->id, $this->ioi->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['inventory.view', 'inventory.transfers.record']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $c = Livewire::actingAs($user)->test(TransferForm::class);

        $this->assertSame(['IOI', 'KLCC'],
            $c->viewData('sourceOutlets')->pluck('name')->sort()->values()->all());
    }
}
