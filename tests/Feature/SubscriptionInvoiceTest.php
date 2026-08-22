<?php

namespace Tests\Feature;

use App\Livewire\Admin\Invoices\Form as InvoiceForm;
use App\Livewire\Admin\Invoices\Index as InvoiceIndex;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Subscription invoicing — the platform billing its tenants.
 *
 * The rules worth holding are the ones that are easy to break by making the
 * screen more convenient: a webhook retry must not raise a second invoice, an
 * issued invoice must not be editable, a number must never be reused or
 * deleted, and a customer must never see a draft.
 */
class SubscriptionInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Plan $plan;
    private Subscription $subscription;
    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'                => 'Warung Pak Din Sdn Bhd',
            'slug'                => Str::slug('Warung Pak Din') . '-' . uniqid(),
            'registration_number' => '202301012345',
            'billing_address'     => '12 Jalan Ampang, 50450 Kuala Lumpur',
            'currency'            => 'MYR',
            'is_active'           => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->plan = Plan::create([
            'name' => 'Growth', 'slug' => 'growth-' . uniqid(),
            'price_monthly' => 129.00, 'price_yearly' => 1290.00, 'currency' => 'MYR',
            'is_active' => true, 'is_public' => true,
        ]);

        $this->subscription = Subscription::create([
            'company_id'           => $this->company->id,
            'plan_id'              => $this->plan->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'billing_cycle'        => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
        ]);

        $this->customer = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
        ]);
        $this->customer->companies()->syncWithoutDetaching([$this->company->id]);

        $this->admin = User::factory()->create(['company_id' => null]);
        setPermissionsTeamId(null);
        $this->admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    // ── Numbering ──────────────────────────────────────────────────────────

    public function test_invoice_numbers_run_in_sequence_within_a_year(): void
    {
        $first  = $this->service()->createManual($this->company, [$this->line()]);
        $second = $this->service()->createManual($this->company, [$this->line()]);

        $year = date('Y');
        $this->assertSame("INV-{$year}-0001", $first->invoice_number);
        $this->assertSame("INV-{$year}-0002", $second->invoice_number);
    }

    /**
     * A December invoice raised on 2 January belongs to December's sequence.
     * Numbering off the calendar year instead would produce INV-2027-0001
     * dated 2026-12-28, which is a bug an accountant finds before you do.
     */
    public function test_a_backdated_invoice_is_numbered_in_its_issue_year(): void
    {
        $this->service()->createManual($this->company, [$this->line()]);

        $backdated = $this->service()->createManual($this->company, [$this->line()], [
            'issued_at' => '2024-12-28',
            'status'    => Invoice::STATUS_ISSUED,
        ]);

        $this->assertSame('INV-2024-0001', $backdated->invoice_number);
    }

    // ── The webhook path ───────────────────────────────────────────────────

    public function test_a_repeated_webhook_does_not_raise_a_second_invoice(): void
    {
        $payment = Payment::create([
            'company_id'      => $this->company->id,
            'subscription_id' => $this->subscription->id,
            'amount'          => 129.00,
            'currency'        => 'MYR',
            'status'          => Payment::STATUS_COMPLETED,
            'paid_at'         => now(),
        ]);

        $first  = $this->service()->createFromPayment($payment);
        $second = $this->service()->createFromPayment($payment);

        $this->assertSame($first->id, $second->id, 'A gateway retry raised a duplicate invoice.');
        $this->assertSame(1, Invoice::count());
        $this->assertSame(Invoice::STATUS_PAID, $first->status);
        $this->assertSame($this->subscription->id, $first->subscription_id);
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function test_an_issued_invoice_cannot_be_edited(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->updateDraft($invoice, [$this->line()]);
    }

    public function test_a_paid_invoice_cannot_be_voided(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);
        $this->service()->markPaid($invoice);

        $this->expectException(\RuntimeException::class);
        $this->service()->void($invoice->refresh(), 'changed my mind');
    }

    /**
     * A number removed from the sequence is a gap, and a gap is what an audit
     * asks about. Voiding keeps the row and records why.
     */
    public function test_voiding_keeps_the_invoice_number(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);
        $number = $invoice->invoice_number;

        $this->service()->void($invoice, 'Superseded');

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => $number,
            'status'         => Invoice::STATUS_VOID,
            'void_reason'    => 'Superseded',
        ]);
    }

    public function test_settling_writes_a_matching_payment(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()], [
            'status'   => Invoice::STATUS_ISSUED,
            'tax_rate' => 6,
        ]);

        $this->service()->markPaid($invoice, now(), 'bank_transfer', 'MBB-99871');
        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertNotNull($invoice->payment, 'No payment row was written for a settled invoice.');
        $this->assertSame(Payment::STATUS_COMPLETED, $invoice->payment->status);
        $this->assertEquals($invoice->total, $invoice->payment->amount);
        $this->assertSame('MBB-99871', $invoice->payment->metadata['reference']);
    }

    // ── Money ──────────────────────────────────────────────────────────────

    public function test_tax_and_totals_are_rounded_to_cents(): void
    {
        $totals = $this->service()->totals([
            ['description' => 'Plan', 'quantity' => 3, 'unit_price' => 33.333],
        ], 6);

        // 3 × 33.33 = 99.99, and 6% of that is 6.00 (5.9994 rounded).
        $this->assertSame(99.99, $totals['amount']);
        $this->assertSame(6.00, $totals['tax_amount']);
        $this->assertSame(105.99, $totals['total']);
    }

    public function test_a_line_with_no_description_is_dropped_rather_than_priced(): void
    {
        $totals = $this->service()->totals([
            ['description' => 'Plan', 'quantity' => 1, 'unit_price' => 100],
            ['description' => '',     'quantity' => 1, 'unit_price' => 999],
        ]);

        $this->assertSame(100.0, $totals['amount']);
    }

    /**
     * Companies rename and move. A PDF reissued a year later must show the
     * details the invoice was raised with, not today's.
     */
    public function test_the_billing_address_is_snapshotted_at_creation(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()]);

        $this->company->update(['name' => 'Renamed Holdings Bhd', 'billing_address' => 'Somewhere else']);

        $this->assertSame('Warung Pak Din Sdn Bhd', $invoice->fresh()->bill_to['name']);
        $this->assertSame('12 Jalan Ampang, 50450 Kuala Lumpur', $invoice->fresh()->bill_to['address']);
    }

    // ── What the customer may see ──────────────────────────────────────────

    public function test_a_draft_is_hidden_from_the_customer(): void
    {
        $draft  = $this->service()->createManual($this->company, [$this->line()]);
        $issued = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $visible = Invoice::visibleToCustomer()->pluck('id')->all();

        $this->assertContains($issued->id, $visible);
        $this->assertNotContains($draft->id, $visible);
    }

    public function test_a_customer_cannot_download_a_draft_pdf(): void
    {
        $draft = $this->service()->createManual($this->company, [$this->line()]);

        $this->actingAs($this->customer)
            ->get(route('invoices.pdf', $draft->id))
            ->assertNotFound();
    }

    public function test_a_customer_cannot_download_another_companys_invoice(): void
    {
        $other = Company::create([
            'name' => 'Someone Else', 'slug' => 'other-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $theirs = $this->service()->createManual($other, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $this->actingAs($this->customer)
            ->get(route('invoices.pdf', $theirs->id))
            ->assertForbidden();
    }

    public function test_a_customer_can_download_their_own_issued_invoice(): void
    {
        $invoice = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $response = $this->actingAs($this->customer)->get(route('invoices.pdf', $invoice->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_an_admin_can_download_any_invoice_including_a_draft(): void
    {
        $draft = $this->service()->createManual($this->company, [$this->line()]);

        $this->actingAs($this->admin)
            ->get(route('invoices.pdf', $draft->id))
            ->assertOk();
    }

    // ── The admin screens ──────────────────────────────────────────────────

    public function test_the_admin_list_is_closed_to_a_tenant_user(): void
    {
        $this->actingAs($this->customer)->get('/admin/invoices')->assertForbidden();
    }

    public function test_the_form_raises_an_invoice_with_the_plan_prefilled(): void
    {
        Livewire::actingAs($this->admin)
            ->test(InvoiceForm::class)
            ->set('company_id', $this->company->id)
            // Choosing the company offers the live subscription and fills the
            // line from the plan price — nobody retypes a number the product
            // already knows.
            ->assertSet('subscription_id', $this->subscription->id)
            ->assertSet('lines.0.unit_price', '129')
            ->set('tax_rate', '6')
            ->set('issueNow', true)
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::firstOrFail();

        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame($this->subscription->id, $invoice->subscription_id);
        $this->assertEquals(129.00, (float) $invoice->amount);
        $this->assertEquals(7.74, (float) $invoice->tax_amount);
        $this->assertEquals(136.74, (float) $invoice->total);
    }

    public function test_the_form_refuses_a_line_with_no_description(): void
    {
        Livewire::actingAs($this->admin)
            ->test(InvoiceForm::class)
            ->set('company_id', $this->company->id)
            ->set('lines', [['description' => '', 'quantity' => '1', 'unit_price' => '10']])
            ->call('save')
            ->assertHasErrors('lines.0.description');
    }

    public function test_the_list_only_lets_a_draft_be_deleted(): void
    {
        $draft  = $this->service()->createManual($this->company, [$this->line()]);
        $issued = $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);

        Livewire::actingAs($this->admin)
            ->test(InvoiceIndex::class)
            ->call('deleteDraft', $draft->id)
            ->call('deleteDraft', $issued->id);

        $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);
        $this->assertDatabaseHas('invoices', ['id' => $issued->id]);
    }

    /**
     * Drafts have no issued_at. Ordering the list on that column alone drops
     * them below everything else — putting the one thing needing action where
     * it is hardest to find.
     */
    public function test_drafts_appear_at_the_top_of_the_list_with_everything_else(): void
    {
        $this->service()->createManual($this->company, [$this->line()], [
            'status' => Invoice::STATUS_ISSUED,
        ]);
        $draft = $this->service()->createManual($this->company, [$this->line()]);

        Livewire::actingAs($this->admin)
            ->test(InvoiceIndex::class)
            ->assertSeeInOrder([$draft->invoice_number, 'INV-' . date('Y') . '-0001']);
    }

    /** @return array{description: string, quantity: int, unit_price: float} */
    private function line(): array
    {
        return ['description' => 'Servora Growth Plan — Monthly', 'quantity' => 1, 'unit_price' => 129.00];
    }
}
