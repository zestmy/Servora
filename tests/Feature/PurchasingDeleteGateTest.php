<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\Index;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Deleting a purchasing DRAFT needs more than being able to look at it.
 *
 * REPORTED BY AUDIT. `Index::delete()` checked outlet access and the draft
 * status and nothing else, so an account holding only `purchasing.view` —
 * read-only, by its own help text — could destroy a colleague's draft order.
 * `deletePr()` had the same hole on its draft branch: the permission check
 * there only guarded cancelled and rejected requests.
 *
 * What made it look accidental rather than intended is that
 * `purchasing.delete` already existed and WAS enforced on the siblings —
 * non-draft requests, and rolling back an approved order. Only the draft paths
 * were open.
 *
 * The floor is AUTHORSHIP OF THAT DOCUMENT TYPE, not `purchasing.delete`:
 * requiring the delete capability would take draft cleanup away from the very
 * people who create drafts all day.
 */
class PurchasingDeleteGateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Purch Co', 'slug' => Str::slug('Purch Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'company_id' => $this->company->id, 'name' => 'ACME', 'is_active' => true,
        ]);
    }

    /** @param list<string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach ($abilities as $a) {
            Permission::findOrCreate($a, 'web');
        }
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function draftPo(User $creator): PurchaseOrder
    {
        return PurchaseOrder::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_id' => $this->supplier->id, 'po_number' => 'PO-' . Str::random(6),
            'status' => 'draft', 'order_date' => '2026-07-01', 'created_by' => $creator->id,
        ]);
    }

    private function draftPr(User $creator): PurchaseRequest
    {
        return PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-' . Str::random(6), 'status' => 'draft',
            'requested_date' => '2026-07-01', 'created_by' => $creator->id,
        ]);
    }

    // ── The hole ──────────────────────────────────────────────────────────

    public function test_read_only_access_cannot_delete_a_draft_order(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $po     = $this->draftPo($author);

        $reader = $this->user(['purchasing.view']);

        Livewire::actingAs($reader)->test(Index::class)->call('delete', $po->id);

        $this->assertNotSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_read_only_access_cannot_delete_a_draft_request(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $pr     = $this->draftPr($author);

        $reader = $this->user(['purchasing.view']);

        Livewire::actingAs($reader)->test(Index::class)->call('deletePr', $pr->id);

        $this->assertNotSoftDeleted('purchase_requests', ['id' => $pr->id]);
    }

    /** Hiding the button is not the check, but it should still be hidden. */
    public function test_the_draft_delete_buttons_are_hidden_from_a_reader(): void
    {
        $reader = Livewire::actingAs($this->user(['purchasing.view']))->test(Index::class);
        $reader->assertSet('tab', $reader->get('tab'));

        $this->assertFalse($reader->viewData('canDeleteDraftPo'));
        $this->assertFalse($reader->viewData('canDeleteDraftPr'));

        $buyer = Livewire::actingAs($this->user(['purchasing.view', 'purchasing.orders.create']))
            ->test(Index::class);

        $this->assertTrue($buyer->viewData('canDeleteDraftPo'));
    }

    // ── And what must keep working ────────────────────────────────────────

    public function test_somebody_who_raises_orders_can_still_bin_a_draft(): void
    {
        $buyer = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $po    = $this->draftPo($buyer);

        Livewire::actingAs($buyer)->test(Index::class)->call('delete', $po->id);

        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_somebody_who_amends_orders_can_also_bin_a_draft(): void
    {
        $editor = $this->user(['purchasing.view', 'purchasing.orders.edit']);
        $po     = $this->draftPo($editor);

        Livewire::actingAs($editor)->test(Index::class)->call('delete', $po->id);

        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    public function test_somebody_who_raises_requests_can_still_bin_a_draft(): void
    {
        $requester = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $pr        = $this->draftPr($requester);

        Livewire::actingAs($requester)->test(Index::class)->call('deletePr', $pr->id);

        $this->assertSoftDeleted('purchase_requests', ['id' => $pr->id]);
    }

    /** The delete capability passes everywhere, as it does elsewhere here. */
    public function test_delete_capability_alone_is_enough(): void
    {
        $author  = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $po      = $this->draftPo($author);
        $cleaner = $this->user(['purchasing.view', 'purchasing.delete']);

        Livewire::actingAs($cleaner)->test(Index::class)->call('delete', $po->id);

        $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
    }

    // ── Unchanged rules ───────────────────────────────────────────────────

    /** Committed spend is cancelled or rolled back, never deleted. */
    public function test_a_non_draft_order_is_still_refused(): void
    {
        $buyer = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $po    = $this->draftPo($buyer);
        $po->update(['status' => 'approved']);

        Livewire::actingAs($buyer)->test(Index::class)->call('delete', $po->id);

        $this->assertNotSoftDeleted('purchase_orders', ['id' => $po->id]);

        // The refusal now also flashes a reason — it used to return silently,
        // which reads as the app being broken. Not asserted here: Livewire's
        // test harness does not surface a component flash the way a full page
        // request does, and the behaviour that matters is the refusal.
    }

    /** Removing a cancelled request is still the destructive-cleanup gate. */
    public function test_a_cancelled_request_still_needs_the_delete_capability(): void
    {
        $requester = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $pr        = $this->draftPr($requester);
        $pr->update(['status' => 'cancelled']);

        Livewire::actingAs($requester)->test(Index::class)->call('deletePr', $pr->id);
        $this->assertNotSoftDeleted('purchase_requests', ['id' => $pr->id]);

        $cleaner = $this->user(['purchasing.view', 'purchasing.delete']);
        Livewire::actingAs($cleaner)->test(Index::class)->call('deletePr', $pr->id);
        $this->assertSoftDeleted('purchase_requests', ['id' => $pr->id]);
    }

    /** Outlet access is still required on top of the ability. */
    public function test_an_inaccessible_outlet_is_still_refused(): void
    {
        $other = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);

        $buyer = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $buyer->companies()->syncWithoutDetaching([$this->company->id]);
        $buyer->outlets()->sync([$this->outlet->id]);
        setPermissionsTeamId($this->company->id);
        foreach (['purchasing.view', 'purchasing.orders.create'] as $a) {
            Permission::findOrCreate($a, 'web');
        }
        $buyer->givePermissionTo(['purchasing.view', 'purchasing.orders.create']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $po = PurchaseOrder::create([
            'company_id' => $this->company->id, 'outlet_id' => $other->id,
            'supplier_id' => $this->supplier->id, 'po_number' => 'PO-OTHER',
            'status' => 'draft', 'order_date' => '2026-07-01', 'created_by' => $buyer->id,
        ]);

        Livewire::actingAs($buyer)->test(Index::class)->call('delete', $po->id);

        $this->assertNotSoftDeleted('purchase_orders', ['id' => $po->id]);
    }
}
