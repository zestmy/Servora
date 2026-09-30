<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\CreditNoteForm;
use App\Livewire\Purchasing\Index;
use App\Livewire\Purchasing\InvoiceReceive;
use App\Livewire\Purchasing\OrderForm;
use App\Livewire\Purchasing\PurchaseRequestForm;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StockTransferOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Purchasing writes need their own ability, not just `purchasing.view`.
 *
 * Livewire re-applies a page route's `can:` middleware on every action, so an
 * action is only as protected as the page it lives on. The purchasing index,
 * credit notes and AI invoice receive pages all sit behind `purchasing.view`
 * — read-only by its own help text — while carrying buttons that submit,
 * cancel, receive, issue and approve. Each of those now names its ability.
 *
 * The order and request edit pages were loosened to `purchasing.view` on
 * purpose, so a reader can open a document from the list: they render
 * read-only and their save() still demands the edit ability.
 */
class PurchasingWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Perm Co', 'slug' => Str::slug('Perm Co') . '-' . uniqid(),
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
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
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

    private function reader(): User
    {
        return $this->user(['purchasing.view']);
    }

    private function po(User $creator, string $status = 'draft'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'supplier_id' => $this->supplier->id, 'po_number' => 'PO-' . Str::random(6),
            'status' => $status, 'order_date' => now()->toDateString(), 'created_by' => $creator->id,
        ]);
    }

    private function pr(User $creator, string $status = 'draft'): PurchaseRequest
    {
        return PurchaseRequest::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'pr_number' => 'PR-' . Str::random(6), 'status' => $status,
            'requested_date' => now()->toDateString(), 'created_by' => $creator->id,
        ]);
    }

    private function sto(User $creator, string $status): StockTransferOrder
    {
        $cpuId = DB::table('central_purchasing_units')->insertGetId([
            'company_id' => $this->company->id, 'name' => 'CPU', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return StockTransferOrder::create([
            'company_id' => $this->company->id, 'cpu_id' => $cpuId,
            'to_outlet_id' => $this->outlet->id, 'sto_number' => 'STO-' . Str::random(6),
            'status' => $status, 'transfer_date' => now()->toDateString(), 'created_by' => $creator->id,
        ]);
    }

    // ── Index actions: read-only is refused ──────────────────────────────

    public function test_read_only_cannot_submit_or_cancel_a_purchase_order(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $draft  = $this->po($author);

        Livewire::actingAs($this->reader())->test(Index::class)
            ->call('submitPo', $draft->id)
            ->assertForbidden();

        Livewire::actingAs($this->reader())->test(Index::class)
            ->call('cancel', $draft->id)
            ->assertForbidden();

        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_an_order_author_can_still_submit(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $draft  = $this->po($author);

        Livewire::actingAs($author)->test(Index::class)
            ->call('submitPo', $draft->id)
            ->assertOk();

        $this->assertNotSame('draft', $draft->fresh()->status);
    }

    public function test_read_only_cannot_submit_cancel_or_revert_a_request(): void
    {
        $author    = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $draft     = $this->pr($author);
        $submitted = $this->pr($author, 'submitted');
        $approved  = $this->pr($author, 'approved');

        Livewire::actingAs($this->reader())->test(Index::class)->call('submitPr', $draft->id)->assertForbidden();
        Livewire::actingAs($this->reader())->test(Index::class)->call('cancelPr', $submitted->id)->assertForbidden();
        Livewire::actingAs($this->reader())->test(Index::class)->call('revertPrToDraft', $approved->id)->assertForbidden();

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('submitted', $submitted->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
    }

    public function test_cancel_pr_now_checks_the_outlet(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $pr     = $this->pr($author, 'submitted');

        $elsewhere = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);
        $outsider = $this->user(['purchasing.view', 'purchasing.requests.edit']);
        $outsider->update(['can_view_all_outlets' => false]);
        $outsider->outlets()->sync([$elsewhere->id]);

        Livewire::actingAs($outsider->fresh())->test(Index::class)->call('cancelPr', $pr->id);

        $this->assertSame('submitted', $pr->fresh()->status);
    }

    public function test_read_only_cannot_send_receive_or_cancel_a_transfer(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.transfers.create']);
        $draft  = $this->sto($author, 'draft');
        $sent   = $this->sto($author, 'sent');

        Livewire::actingAs($this->reader())->test(Index::class)->call('sendSto', $draft->id)->assertForbidden();
        Livewire::actingAs($this->reader())->test(Index::class)->call('cancelSto', $draft->id)->assertForbidden();
        Livewire::actingAs($this->reader())->test(Index::class)->call('receiveSto', $sent->id)->assertForbidden();

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('sent', $sent->fresh()->status);
    }

    public function test_a_receiver_can_still_receive_a_transfer(): void
    {
        $author   = $this->user(['purchasing.view', 'purchasing.transfers.create']);
        $sent     = $this->sto($author, 'sent');
        $receiver = $this->user(['purchasing.view', 'purchasing.receive']);

        Livewire::actingAs($receiver)->test(Index::class)->call('receiveSto', $sent->id)->assertOk();

        $this->assertSame('received', $sent->fresh()->status);
    }

    // ── Order / request edit pages: open read-only, cannot save ──────────

    public function test_read_only_can_open_an_order_but_not_edit_it(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.orders.create']);
        $po     = $this->po($author);
        $reader = $this->reader();

        $html = $this->actingAs($reader)
            ->withSession(['active_outlet_id' => $this->outlet->id])
            ->get(route('purchasing.orders.edit', $po->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($po->po_number, $html);
        $this->assertStringNotContainsString('wire:click="save', $html);

        Livewire::actingAs($reader)->test(OrderForm::class, ['id' => $po->id])
            ->assertViewHas('isEditable', false)
            ->call('save')
            ->assertForbidden();
    }

    public function test_an_order_status_cannot_be_posted_from_the_browser(): void
    {
        $editor = $this->user(['purchasing.view', 'purchasing.orders.edit']);
        $po     = $this->po($editor);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($editor)->test(OrderForm::class, ['id' => $po->id])
            ->set('status', 'approved');
    }

    public function test_read_only_request_page_is_read_only_and_save_is_refused(): void
    {
        $author = $this->user(['purchasing.view', 'purchasing.requests.create']);
        $pr     = $this->pr($author);

        Livewire::actingAs($this->reader())->test(PurchaseRequestForm::class, ['id' => $pr->id])
            ->assertViewHas('isEditable', false)
            ->call('save')
            ->assertForbidden();
    }

    public function test_a_submitted_request_cannot_be_rewritten_by_an_editor(): void
    {
        $editor = $this->user(['purchasing.view', 'purchasing.requests.edit']);
        $pr     = $this->pr($editor);

        $form = Livewire::actingAs($editor)->test(PurchaseRequestForm::class, ['id' => $pr->id]);

        // Somebody submits it while the form is open.
        $pr->update(['status' => 'submitted']);

        $form->call('save')->assertForbidden();
        $this->assertSame('submitted', $pr->fresh()->status);
    }

    // ── Credit notes and AI invoices: invoice work ──────────────────────

    public function test_read_only_cannot_save_a_credit_note(): void
    {
        Livewire::actingAs($this->reader())
            ->test(CreditNoteForm::class)
            ->set('supplier_id', $this->supplier->id)
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_read_only_cannot_approve_upload_or_reject_an_invoice_scan(): void
    {
        $reader = $this->reader();

        Livewire::actingAs($reader)->test(InvoiceReceive::class)->call('approve')->assertForbidden();
        Livewire::actingAs($reader)->test(InvoiceReceive::class)->call('upload')->assertForbidden();
        Livewire::actingAs($reader)->test(InvoiceReceive::class)->call('reject')->assertForbidden();
    }

    public function test_the_scan_id_cannot_be_pointed_at_another_scan(): void
    {
        $clerk = $this->user(['purchasing.view', 'purchasing.invoice']);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($clerk)->test(InvoiceReceive::class)->set('scanId', 999);
    }

    // ── The list hides what the actions refuse ──────────────────────────

    public function test_a_reader_sees_no_write_buttons_on_any_tab(): void
    {
        $author = $this->user([
            'purchasing.view', 'purchasing.orders.create', 'purchasing.requests.create', 'purchasing.transfers.create',
        ]);
        $this->po($author);
        $this->pr($author);
        $this->sto($author, 'draft');
        $this->sto($author, 'sent');

        $reader = $this->reader();

        foreach (['po' => 'submitPo(', 'pr' => 'submitPr(', 'sto' => 'sendSto('] as $tab => $action) {
            $html = Livewire::actingAs($reader)->test(Index::class)->set('tab', $tab)->html();

            $this->assertStringNotContainsString($action, $html, "The {$tab} tab offered {$action} to a reader.");
            $this->assertStringNotContainsString('duplicate=', $html, "The {$tab} tab offered Duplicate to a reader.");
        }

        $html = Livewire::actingAs($reader)->test(Index::class)->set('tab', 'sto')->html();
        $this->assertStringNotContainsString('receiveSto(', $html);

        // The author still sees them.
        $html = Livewire::actingAs($author)->test(Index::class)->set('tab', 'po')->html();
        $this->assertStringContainsString('submitPo(', $html);
    }
}
