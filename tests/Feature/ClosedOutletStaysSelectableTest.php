<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Livewire\Inventory\StockTakeForm;
use App\Livewire\Inventory\TransferForm;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A picker still offers the outlet the record is already filed against.
 *
 * Every outlet dropdown filtered on is_active, which is right for choosing
 * where NEW work goes and wrong for editing something already filed. Close a
 * branch and the select stops containing the value of the record it is
 * editing: the browser falls back to the first option, so the form shows an
 * employee posted somewhere they are not, and one touch of the field moves
 * them there for real.
 *
 * Closing an outlet is a statement about new work, not a retraction of the
 * records already against it. Outlet::selectable() is the one rule — the open
 * ones, plus whatever this record already names — so the pickers cannot drift
 * apart on it.
 */
class ClosedOutletStaysSelectableTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $open;
    private Outlet $closed;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Shut Co', 'slug' => Str::slug('Shut Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->open   = $this->outlet('STILL OPEN', 'OPEN', true);
        $this->closed = $this->outlet('CLOSED BRANCH', 'SHUT', true);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->open->id, $this->closed->id]);

        setPermissionsTeamId($this->company->id);
        foreach ([
            'hr.view', 'hr.employees.manage', 'hr.employment',
            'inventory.view', 'inventory.transfers.record', 'inventory.stock_takes.record',
        ] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(Permission::all()->pluck('name')->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function outlet(string $name, string $code, bool $active): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => $active,
        ]);
    }

    /** Shut the branch AFTER the record was filed against it. */
    private function close(): void
    {
        $this->closed->update(['is_active' => false]);
    }

    // ── The scope itself ──────────────────────────────────────────────────

    public function test_the_rule_offers_open_outlets_and_whatever_is_already_named(): void
    {
        $this->close();

        $this->assertSame(['STILL OPEN'],
            Outlet::selectable()->orderBy('name')->pluck('name')->all(),
            'A closed branch is not somewhere new work goes.');

        $this->assertSame(['CLOSED BRANCH', 'STILL OPEN'],
            Outlet::selectable($this->closed->id)->orderBy('name')->pluck('name')->all(),
            'But it stays offered where it is already the answer.');
    }

    /** Nulls and blanks in the keep list must not widen it to everything. */
    public function test_an_empty_keep_list_does_not_offer_closed_outlets(): void
    {
        $this->close();

        foreach ([[], [null], [0], null, ''] as $keep) {
            $this->assertSame(['STILL OPEN'],
                Outlet::selectable($keep)->orderBy('name')->pluck('name')->all(),
                'Nothing to keep means nothing extra offered.');
        }
    }

    // ── The pickers ───────────────────────────────────────────────────────

    /** The clearest case: an employee posted to a branch that has since shut. */
    public function test_the_employee_form_still_shows_their_own_outlet(): void
    {
        $emp = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->closed->id,
            'name' => 'SITI STAYS', 'is_active' => true, 'join_date' => '2026-01-01',
        ]);

        $this->close();

        $names = Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $emp->id])
            ->viewData('outlets')->pluck('name')->sort()->values()->all();

        $this->assertSame(['CLOSED BRANCH', 'STILL OPEN'], $names,
            'A form that cannot show where somebody works will move them on the next save.');
    }

    /** And a new employee is not offered the closed branch. */
    public function test_a_new_employee_is_not_offered_a_closed_outlet(): void
    {
        $this->close();

        $names = Livewire::actingAs($this->user)->test(EmployeeForm::class)
            ->viewData('outlets')->pluck('name')->all();

        $this->assertSame(['STILL OPEN'], $names);
    }

    public function test_a_transfer_still_shows_both_of_its_own_ends(): void
    {
        $transfer = OutletTransfer::create([
            'company_id' => $this->company->id,
            'from_outlet_id' => $this->open->id, 'to_outlet_id' => $this->closed->id,
            'transfer_number' => 'TRF-TEST-001', 'status' => 'draft',
            'transfer_date' => now()->toDateString(), 'created_by' => $this->user->id,
        ]);

        $this->close();

        $c = Livewire::actingAs($this->user)->test(TransferForm::class, ['id' => $transfer->id]);

        $this->assertContains('CLOSED BRANCH', $c->viewData('destinationOutlets')->pluck('name')->all(),
            'Reopening a transfer must not blank the branch it was sent to.');
    }

    /** The four record forms share one picker, so one case covers the trait. */
    public function test_a_record_form_still_shows_the_outlet_it_was_filed_against(): void
    {
        $this->close();

        $c = Livewire::actingAs($this->user)->test(StockTakeForm::class);
        $c->set('outlet_id', $this->closed->id);

        $m = new \ReflectionMethod(StockTakeForm::class, 'outletOptions');
        $m->setAccessible(true);

        $this->assertContains('CLOSED BRANCH', $m->invoke($c->instance())->pluck('name')->all(),
            'Editing an old stock take must not re-file it somewhere still trading.');
    }
}
