<?php

namespace Tests\Feature;

use App\Livewire\Hr\LabourCostTransferForm;
use App\Livewire\Hr\LabourCostTransfers;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\OvertimeClaim;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Hr\LabourCostTransferCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Labour Cost Transfer: salary and approved OT moved from the outlet that
 * employs someone to the outlet they were lent to.
 *
 * Money in these tests: RM 2,600 a month over the default 26 working days is
 * RM 100 a day; over 8 hours that is RM 12.50 an hour, so a normal-day OT
 * hour at 1.5x is RM 18.75 and a rest-day hour at 2x is RM 25.
 */
class LabourCostTransferTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $home;
    private Outlet $branch;
    private Outlet $events;
    private User $hr;
    private Employee $aisyah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Labour Co', 'slug' => Str::slug('Labour Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->home   = $this->outlet('Home', 'HOME');
        $this->branch = $this->outlet('Branch', 'BR');
        $this->events = $this->outlet('Events', 'EVT');

        foreach (['hr.compensation', 'inventory.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->hr = $this->user(['hr.compensation', 'inventory.view']);

        $this->aisyah = $this->employee('Aisyah', $this->home);
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create(['company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function user(array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => true]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->home->id, $this->branch->id, $this->events->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function employee(string $name, Outlet $outlet, ?float $salary = 2600): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id, 'name' => $name,
            'employment_status' => 'confirmed', 'is_active' => true,
            'basic_salary' => $salary, 'pay_type' => 'monthly',
        ]);
    }

    private function claim(Employee $e, string $date, float $hours, string $type = 'normal_day', string $status = 'approved', string $settlement = 'payroll'): OvertimeClaim
    {
        return OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $e->outlet_id, 'employee_id' => $e->id,
            'submitted_by' => $this->hr->id, 'claim_date' => $date,
            'ot_time_start' => '22:00', 'ot_time_end' => '23:00', 'total_ot_hours' => $hours,
            'ot_type' => $type, 'reason' => 'Event', 'status' => $status, 'settlement' => $settlement,
        ]);
    }

    private function form(string $to = null)
    {
        return Livewire::actingAs($this->hr)->test(LabourCostTransferForm::class)
            ->set('to_outlet_id', (string) ($to ?? $this->events->id))
            ->set('purpose', 'event');
    }

    public function test_a_line_is_days_at_the_daily_rate_plus_approved_payroll_overtime(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);                              // 2 x 18.75 = 37.50
        $this->claim($this->aisyah, '2026-09-03', 1, 'rest_day');                  // 1 x 25.00 = 25.00
        $this->claim($this->aisyah, '2026-09-03', 3, status: 'submitted');         // pending: out
        $this->claim($this->aisyah, '2026-09-03', 4, settlement: 'time_off');      // time off: out
        $this->claim($this->aisyah, '2026-09-10', 5);                              // outside dates: out

        $price = (new LabourCostTransferCalculator($this->company->id))
            ->price($this->aisyah, '2026-09-01', '2026-09-03');

        $this->assertEquals(3, $price['days']);
        $this->assertEquals(100, $price['daily_rate']);
        $this->assertEquals(300, $price['salary_amount']);
        $this->assertEquals(3, $price['ot_hours']);
        $this->assertEquals(62.5, $price['ot_amount']);
        $this->assertEquals(362.5, $price['total_amount']);
        $this->assertCount(2, $price['ot_claim_ids']);
    }

    public function test_saving_stores_a_snapshot_priced_on_the_server(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);

        $this->form()
            ->set('reference', 'Wedding at Dewan Seri')
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')
            ->set('lines.0.date_end', '2026-09-03')
            // A browser cannot set the money: these are overwritten on save.
            ->set('lines.0.daily_rate', 9999)
            ->set('lines.0.total_amount', 9999)
            ->call('save')
            ->assertHasNoErrors();

        $t = LabourCostTransfer::with('lines')->firstOrFail();
        $this->assertSame('draft', $t->status);
        $this->assertSame($this->events->id, $t->to_outlet_id);

        $line = $t->lines->first();
        $this->assertSame($this->home->id, $line->from_outlet_id, 'The sender is the outlet that employs them.');
        $this->assertEquals(3, (float) $line->days);
        $this->assertEquals(100, (float) $line->daily_rate);
        $this->assertEquals(337.5, (float) $line->total_amount);
    }

    public function test_lowering_the_days_leaves_out_rest_days_but_cannot_exceed_the_range(): void
    {
        $c = $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')
            ->set('lines.0.date_end', '2026-09-07');

        $this->assertEquals(7, (float) $c->get('lines')[0]['days']);

        $c->set('lines.0.days', '6');
        $this->assertEquals(600, $c->get('lines')[0]['salary_amount']);

        $c->set('lines.0.days', '9');   // clamped by the preview to the 7 in range
        $this->assertEquals(7, (float) $c->get('lines')[0]['days']);
    }

    public function test_the_same_days_cannot_be_transferred_twice_until_the_first_is_cancelled(): void
    {
        $this->form()->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-05')
            ->call('confirm')->assertHasNoErrors();

        $this->form((string) $this->branch->id)->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-04')->set('lines.0.date_end', '2026-09-06')
            ->call('save')
            ->assertHasErrors('lines.0.date_start');

        $this->assertSame(1, LabourCostTransfer::count());

        LabourCostTransfer::first()->update(['status' => 'cancelled']);

        $this->form((string) $this->branch->id)->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-04')->set('lines.0.date_end', '2026-09-06')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_staff_cannot_be_transferred_to_their_own_outlet(): void
    {
        $this->form((string) $this->home->id)
            ->call('addEmployee', $this->aisyah->id)
            ->call('save')
            ->assertHasErrors('lines.0.employee_id');
    }

    public function test_the_outlet_summary_credits_senders_charges_the_receiver_and_nets_to_zero(): void
    {
        $bob = $this->employee('Bob', $this->branch, 5200);   // RM 200 a day

        $c = $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-02')
            ->call('addEmployee', $bob->id)
            ->set('lines.1.date_start', '2026-09-01')->set('lines.1.date_end', '2026-09-01');

        $summary = collect($c->viewData('summary'))->keyBy('outlet_id');

        $this->assertEquals(400, $summary[$this->events->id]['net']);
        $this->assertEquals(-200, $summary[$this->home->id]['net']);
        $this->assertEquals(-200, $summary[$this->branch->id]['net']);
        $this->assertEquals(0, $summary->sum('net'));
    }

    public function test_the_list_summarises_confirmed_transfers_only(): void
    {
        $this->form()->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', now()->startOfMonth()->toDateString())
            ->set('lines.0.date_end', now()->startOfMonth()->toDateString())
            ->set('transfer_date', now()->toDateString())
            ->call('save');   // draft

        $list = Livewire::actingAs($this->hr)->test(LabourCostTransfers::class);
        $this->assertSame([], $list->viewData('summary'));
        $this->assertSame(1, $list->viewData('transfers')->total());

        LabourCostTransfer::first()->update(['status' => 'confirmed']);
        $list = Livewire::actingAs($this->hr)->test(LabourCostTransfers::class);
        $this->assertEquals(100, collect($list->viewData('summary'))->firstWhere('outlet_id', $this->events->id)['net']);
    }

    // ── Hourly and OT-only lines ──────────────────────────────────────────

    public function test_an_hourly_line_is_hours_at_the_hourly_rate_plus_overtime(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);   // 37.50

        $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->set('lines.0.basis', 'hourly')
            ->set('lines.0.hours', '5')
            ->call('save')
            ->assertHasNoErrors();

        $line = LabourCostTransfer::firstOrFail()->lines()->firstOrFail();
        $this->assertSame('hourly', $line->basis);
        $this->assertEquals(5, (float) $line->hours);
        $this->assertEquals(0, (float) $line->days);
        $this->assertEquals(12.5, (float) $line->hourly_rate);
        $this->assertEquals(62.5, (float) $line->salary_amount);
        $this->assertEquals(100, (float) $line->total_amount);
        $this->assertSame('5 hrs', $line->quantityLabel());
    }

    public function test_an_ot_only_line_moves_the_overtime_and_no_salary(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);                 // 37.50
        $this->claim($this->aisyah, '2026-09-03', 1, 'rest_day');     // 25.00

        $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-05')
            ->set('lines.0.basis', 'ot_only')
            ->call('save')
            ->assertHasNoErrors();

        $line = LabourCostTransfer::firstOrFail()->lines()->firstOrFail();
        $this->assertEquals(0, (float) $line->salary_amount);
        $this->assertEquals(3, (float) $line->ot_hours);
        $this->assertEquals(62.5, (float) $line->total_amount);
    }

    public function test_an_ot_only_line_with_no_overtime_is_refused(): void
    {
        $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.basis', 'ot_only')
            ->call('save')
            ->assertHasErrors('lines.0.basis');

        $this->assertSame(0, LabourCostTransfer::count());
    }

    public function test_part_day_lines_may_share_dates_but_an_ot_claim_moves_only_once(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);

        // Afternoon at the event, hourly: carries the evening's OT claim.
        $this->form()->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->set('lines.0.basis', 'hourly')->set('lines.0.hours', '4')
            ->call('confirm')->assertHasNoErrors();

        // Another hourly stint the same day elsewhere is fine — without the OT.
        $second = $this->form((string) $this->branch->id)->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->set('lines.0.basis', 'hourly')->set('lines.0.hours', '2');
        $this->assertEquals(0, $second->get('lines')[0]['ot_hours'], 'That claim is already on the first transfer.');
        $second->call('save')->assertHasNoErrors();

        // OT only for that day has nothing left to move.
        $this->form((string) $this->branch->id)->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->set('lines.0.basis', 'ot_only')
            ->call('save')->assertHasErrors('lines.0.basis');

        // A whole day on top of part-days is still a double charge.
        $this->form((string) $this->branch->id)->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->call('save')->assertHasErrors('lines.0.date_start');
    }

    public function test_on_one_document_an_ot_claim_goes_to_the_first_line_that_can_carry_it(): void
    {
        $this->claim($this->aisyah, '2026-09-02', 2);   // 37.50

        $c = $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-02')->set('lines.0.date_end', '2026-09-02')
            ->set('lines.0.basis', 'hourly')->set('lines.0.hours', '3')
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.1.date_start', '2026-09-02')->set('lines.1.date_end', '2026-09-02')
            ->set('lines.1.basis', 'hourly')->set('lines.1.hours', '1');

        $this->assertEquals(2, $c->get('lines')[0]['ot_hours']);
        $this->assertEquals(0, $c->get('lines')[1]['ot_hours']);

        $c->call('save')->assertHasNoErrors();
        $this->assertEquals(
            3 * 12.5 + 37.5 + 1 * 12.5,
            (float) LabourCostTransfer::firstOrFail()->lines()->sum('total_amount')
        );
    }

    public function test_the_pdf_downloads_and_is_behind_the_pay_gate(): void
    {
        $this->form()->call('addEmployee', $this->aisyah->id)->call('confirm');
        $t = LabourCostTransfer::firstOrFail();

        $this->actingAs($this->hr)->get(route('hr.labour-transfers.pdf', $t->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $noPay = $this->user(['inventory.view']);
        $this->actingAs($noPay)->get(route('hr.labour-transfers.pdf', $t->id))->assertForbidden();
        $this->actingAs($noPay)->get(route('hr.labour-transfers'))->assertForbidden();
    }

    public function test_the_period_summary_pdf_downloads_for_the_filters_on_screen(): void
    {
        $this->form()->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-02')
            ->set('transfer_date', '2026-09-02')
            ->call('confirm');

        $this->actingAs($this->hr)
            ->get(route('hr.labour-transfers.summary-pdf', ['from' => '2026-09-01', 'to' => '2026-09-30', 'outlet' => $this->home->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Same set the list counts: confirmed, dated in the period.
        $this->assertSame(1, LabourCostTransfer::query()
            ->forPeriod('2026-09-01', '2026-09-30', [$this->home->id])->where('status', 'confirmed')->count());
        $this->assertSame(0, LabourCostTransfer::query()
            ->forPeriod('2026-10-01', '2026-10-31', [$this->home->id])->count());

        // An outlet in the URL is not access to it.
        $limited = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => false]);
        $limited->companies()->syncWithoutDetaching([$this->company->id]);
        $limited->outlets()->sync([$this->branch->id]);
        setPermissionsTeamId($this->company->id);
        $limited->givePermissionTo('hr.compensation');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($limited)
            ->get(route('hr.labour-transfers.summary-pdf', ['outlet' => $this->home->id]))
            ->assertForbidden();

        $this->actingAs($this->user(['inventory.view']))
            ->get(route('hr.labour-transfers.summary-pdf'))
            ->assertForbidden();
    }

    public function test_the_period_summary_downloads_as_a_workbook_with_live_totals(): void
    {
        $bob = $this->employee('Bob', $this->branch, 5200);   // RM 200 a day
        $this->claim($bob, '2026-09-03', 2);                  // RM 25/hr x 1.5 x 2 = RM 75

        $this->form()
            ->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-02')   // RM 200
            ->call('addEmployee', $bob->id)
            ->set('lines.1.date_start', '2026-09-03')->set('lines.1.date_end', '2026-09-03')
            ->set('lines.1.basis', 'ot_only')                                                   // RM 75
            ->set('transfer_date', '2026-09-03')
            ->call('confirm')->assertHasNoErrors();

        $response = $this->actingAs($this->hr)
            ->get(route('hr.labour-transfers.summary-excel', ['from' => '2026-09-01', 'to' => '2026-09-30']));
        $response->assertOk();
        $this->assertStringContainsString('Labour-Cost-Transfer-Summary-2026-09-01-to-2026-09-30.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'lct') . '.xlsx';
        file_put_contents($path, $response->streamedContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['Net by Outlet', 'By Outlet', 'Lines'], $book->getSheetNames());

        // Net by Outlet: find each outlet's row and read the live net formula.
        $net = $book->getSheetByName('Net by Outlet');
        $byName = [];
        foreach ($net->getRowIterator() as $r) {
            $i = $r->getRowIndex();
            $byName[(string) $net->getCell("A{$i}")->getValue()] = $i;
        }
        $this->assertEquals(275, $net->getCell('H' . $byName['Events'])->getCalculatedValue());
        $this->assertEquals(-200, $net->getCell('H' . $byName['Home'])->getCalculatedValue());
        $this->assertEquals(-75, $net->getCell('H' . $byName['Branch'])->getCalculatedValue());
        $this->assertEquals(0, $net->getCell('H' . $byName['TOTAL'])->getCalculatedValue(), 'The net column sums to zero.');

        // Lines: one row per line, total = salary + OT, SUBTOTAL at the foot.
        $lines = $book->getSheetByName('Lines');
        $rows = [];
        foreach ($lines->getRowIterator() as $r) {
            $i = $r->getRowIndex();
            $rows[(string) $lines->getCell("A{$i}")->getValue()][] = $i;
        }
        $this->assertCount(2, $rows[LabourCostTransfer::first()->transfer_number]);
        $this->assertEquals(275, $lines->getCell('R' . $rows['TOTAL'][0])->getCalculatedValue());
        $this->assertSame('OT only', $lines->getCell('K' . $rows[LabourCostTransfer::first()->transfer_number][1])->getValue());

        // Same gate as the PDF.
        $this->actingAs($this->user(['inventory.view']))
            ->get(route('hr.labour-transfers.summary-excel'))
            ->assertForbidden();
    }

    // ── Editing and deleting after confirmation ───────────────────────────

    private function confirmedTransfer(): LabourCostTransfer
    {
        $this->form()->call('addEmployee', $this->aisyah->id)
            ->set('lines.0.date_start', '2026-09-01')->set('lines.0.date_end', '2026-09-02')
            ->call('confirm')->assertHasNoErrors();

        return LabourCostTransfer::firstOrFail();
    }

    private function admin(): User
    {
        Permission::findOrCreate('hr.compensation.transfers.manage', 'web');

        return $this->user(['hr.compensation', 'hr.compensation.transfers.manage', 'inventory.view']);
    }

    public function test_a_company_admin_can_correct_a_confirmed_transfer_and_it_stays_confirmed(): void
    {
        $t = $this->confirmedTransfer();
        $this->assertEquals(200, (float) $t->lines()->sum('total_amount'));

        Livewire::actingAs($this->admin())->test(LabourCostTransferForm::class, ['id' => $t->id])
            ->call('startEditing')
            ->assertSet('editing', true)
            ->set('lines.0.date_end', '2026-09-04')   // 4 days now
            ->call('saveChanges')
            ->assertHasNoErrors();

        $t->refresh();
        $this->assertSame('confirmed', $t->status);
        $this->assertEquals(400, (float) $t->lines()->sum('total_amount'));

        $this->assertTrue(
            \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('auditable_type', LabourCostTransfer::class)->where('auditable_id', $t->id)
                ->where('event', 'edited_after_confirm')->exists(),
            'A correction to a confirmed transfer is on the record.'
        );
    }

    public function test_without_the_ability_a_confirmed_transfer_stays_locked(): void
    {
        $t = $this->confirmedTransfer();

        $c = Livewire::actingAs($this->hr)->test(LabourCostTransferForm::class, ['id' => $t->id]);
        $c->call('startEditing')->assertForbidden();

        // Forcing the flag from the browser does not unlock anything.
        $c = Livewire::actingAs($this->hr)->test(LabourCostTransferForm::class, ['id' => $t->id])
            ->set('editing', true)
            ->set('lines.0.date_end', '2026-09-10');
        $c->call('saveChanges')->assertForbidden();
        $this->assertEquals(200, (float) $t->lines()->sum('total_amount'));

        Livewire::actingAs($this->hr)->test(LabourCostTransferForm::class, ['id' => $t->id])
            ->call('deleteTransfer')->assertForbidden();
        $this->assertNotNull(LabourCostTransfer::find($t->id));
    }

    public function test_deleting_follows_the_same_rule_and_takes_the_cost_out_of_the_reports(): void
    {
        // A draft is anyone's to delete.
        $this->form()->call('addEmployee', $this->aisyah->id)->call('save');
        $draft = LabourCostTransfer::firstOrFail();
        Livewire::actingAs($this->hr)->test(LabourCostTransfers::class)->call('deleteTransfer', $draft->id);
        $this->assertNull(LabourCostTransfer::find($draft->id));

        $t = $this->confirmedTransfer();
        Livewire::actingAs($this->hr)->test(LabourCostTransfers::class)->call('deleteTransfer', $t->id)->assertForbidden();

        $this->assertNotEmpty(\App\Services\Hr\LabourCostTransferLedger::byOutlet($this->company->id, '2026-09-01', '2026-09-30'));

        Livewire::actingAs($this->admin())->test(LabourCostTransferForm::class, ['id' => $t->id])
            ->call('deleteTransfer')
            ->assertRedirect(route('hr.labour-transfers'));

        $this->assertNull(LabourCostTransfer::find($t->id));
        $this->assertSame([], \App\Services\Hr\LabourCostTransferLedger::byOutlet($this->company->id, '2026-09-01', '2026-09-30'));
    }

    public function test_a_stock_transfer_downloads_as_a_workbook(): void
    {
        $kg  = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight', 'base_unit_factor' => 1000]);
        $pcs = UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'pcs', 'type' => 'count', 'base_unit_factor' => 1]);
        $flour = \App\Models\Ingredient::create([
            'company_id' => $this->company->id, 'name' => 'Flour', 'code' => 'FL01',
            'base_uom_id' => $kg->id, 'recipe_uom_id' => $kg->id, 'current_cost' => 4, 'is_active' => true,
        ]);
        $transfer = OutletTransfer::create([
            'company_id' => $this->company->id, 'from_outlet_id' => $this->home->id, 'to_outlet_id' => $this->branch->id,
            'transfer_number' => 'TRF-TEST-002', 'status' => 'received', 'transfer_date' => '2026-09-01',
            'created_by' => $this->hr->id,
        ]);
        $transfer->lines()->create(['ingredient_id' => $flour->id, 'uom_id' => $kg->id, 'quantity' => 12.5, 'unit_cost' => 4]);
        $transfer->lines()->create(['custom_name' => 'Cake stand', 'uom_id' => $pcs->id, 'quantity' => 2, 'unit_cost' => 12]);

        $response = $this->actingAs($this->hr)->get(route('inventory.transfers.excel', $transfer->id));
        $response->assertOk();
        $this->assertStringContainsString('Stock-Transfer-TRF-TEST-002.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'trf') . '.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $rows = [];
        foreach ($sheet->getRowIterator() as $r) {
            $i = $r->getRowIndex();
            $rows[(string) $sheet->getCell("B{$i}")->getValue() ?: (string) $sheet->getCell("A{$i}")->getValue()] = $i;
        }

        $this->assertSame('Market List', $sheet->getCell('C' . $rows['FLOUR'])->getValue());
        $this->assertEquals(50, $sheet->getCell('H' . $rows['FLOUR'])->getCalculatedValue());
        $this->assertSame('Custom', $sheet->getCell('C' . $rows['Cake stand'])->getValue());
        $this->assertEquals(74, $sheet->getCell('H' . $rows['TOTAL'])->getCalculatedValue(), 'Live SUM of quantity x unit cost.');

        // Same access rule as the PDF: someone who can see neither end is refused.
        $outsider = User::factory()->create(['company_id' => $this->company->id, 'can_view_all_outlets' => false]);
        $outsider->companies()->syncWithoutDetaching([$this->company->id]);
        $outsider->outlets()->sync([$this->events->id]);
        setPermissionsTeamId($this->company->id);
        $outsider->givePermissionTo('inventory.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($outsider)->get(route('inventory.transfers.excel', $transfer->id))->assertForbidden();
    }

    public function test_the_stock_transfer_pdf_downloads(): void
    {
        $pcs = UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'pcs', 'type' => 'count', 'base_unit_factor' => 1]);
        $transfer = OutletTransfer::create([
            'company_id' => $this->company->id, 'from_outlet_id' => $this->home->id, 'to_outlet_id' => $this->branch->id,
            'transfer_number' => 'TRF-TEST-001', 'status' => 'in_transit', 'transfer_date' => '2026-09-01',
            'created_by' => $this->hr->id,
        ]);
        $transfer->lines()->create(['custom_name' => 'Cake stand', 'uom_id' => $pcs->id, 'quantity' => 2, 'unit_cost' => 12]);

        $this->actingAs($this->hr)->get(route('inventory.transfers.pdf', $transfer->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
