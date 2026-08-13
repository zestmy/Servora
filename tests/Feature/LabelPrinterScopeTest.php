<?php

namespace Tests\Feature;

use App\Livewire\Labels\PrintScreen;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LabelPrinter;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * You print on a printer in the room you are standing in.
 *
 * REPORTED FROM THE CENTRAL KITCHEN: the Printer list offered every printer in
 * the company, and "Prepared by" followed whichever was chosen. Labels print on
 * paper in a room — the wrong room is not a lesser version of the right one,
 * it is a label nobody will ever see, and a chef signing for food prepped in a
 * restaurant they have never worked in.
 *
 * The default was worse than the list: it preferred the active outlet's
 * printer and then fell back to ANY printer in the company, so a kitchen with
 * none of its own silently pre-selected one in a restaurant.
 *
 * availableOutletIds() is the existing answer to "which outlets is this screen
 * about" — in kitchen mode it collapses to the kitchen's own outlet — so this
 * asks the same question the rest of the app already asks.
 */
class LabelPrinterScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $restaurant;
    private Outlet $kitchenOutlet;
    private CentralKitchen $kitchen;
    private LabelPrinter $restaurantPrinter;
    private LabelPrinter $kitchenPrinter;
    private Employee $waiter;
    private Employee $chef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Label Co', 'slug' => Str::slug('Label Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->restaurant    = $this->outlet('KLCC', 'KLCC');
        $this->kitchenOutlet = $this->outlet('CENTRAL KITCHEN', 'CK');

        $this->kitchen = CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => 'CK Cheras', 'code' => 'CKC',
            'outlet_id' => $this->kitchenOutlet->id, 'is_active' => true,
        ]);

        $this->restaurantPrinter = $this->printer('KLCC DELI', $this->restaurant);
        $this->kitchenPrinter    = $this->printer('CK LINE 1', $this->kitchenOutlet);

        $this->waiter = $this->employee('WAITER WONG', $this->restaurant);
        $this->chef   = $this->employee('CHEF CHIN', $this->kitchenOutlet);
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function printer(string $name, Outlet $outlet): LabelPrinter
    {
        return LabelPrinter::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id, 'name' => $name,
            'width_mm' => 50, 'height_mm' => 25, 'is_active' => true, 'driver' => 'browser',
        ]);
    }

    private function employee(string $name, Outlet $outlet): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2026-01-01',
        ]);
    }

    /** A user attached only to the kitchen's outlet, standing in the kitchen. */
    private function kitchenUser(): User
    {
        $u = $this->user([$this->kitchenOutlet->id]);
        $this->kitchen->users()->syncWithoutDetaching([$u->id => ['role' => 'chef']]);
        session(['workspace_mode' => 'kitchen', 'active_kitchen_id' => $this->kitchen->id]);

        return $u;
    }

    private function user(array $outletIds): User
    {
        $u = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $u->companies()->syncWithoutDetaching([$this->company->id]);
        $u->outlets()->sync($outletIds);

        setPermissionsTeamId($this->company->id);
        foreach (['labels.print', 'labels.view_log'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $u->givePermissionTo(['labels.print', 'labels.view_log']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    // ── The reported bug ──────────────────────────────────────────────────

    public function test_the_kitchen_is_offered_only_its_own_printer(): void
    {
        $names = Livewire::actingAs($this->kitchenUser())->test(PrintScreen::class)
            ->viewData('printers')->pluck('name')->all();

        $this->assertSame(['CK LINE 1'], $names,
            'A restaurant printer is a label the kitchen will never collect.');
    }

    /** The old default reached past the workspace for ANY company printer. */
    public function test_the_default_printer_is_one_the_kitchen_can_reach(): void
    {
        $this->assertSame(
            $this->kitchenPrinter->id,
            Livewire::actingAs($this->kitchenUser())->test(PrintScreen::class)->get('printerId')
        );
    }

    public function test_prepared_by_offers_only_staff_of_that_outlet(): void
    {
        $names = Livewire::actingAs($this->kitchenUser())->test(PrintScreen::class)
            ->viewData('employees')->pluck('name')->all();

        $this->assertSame(['CHEF CHIN'], $names,
            'The label says who made the food, and the food was made where the printer is.');
    }

    // ── The same rule on an outlet ────────────────────────────────────────

    public function test_an_outlet_user_is_offered_only_their_own(): void
    {
        $c = Livewire::actingAs($this->user([$this->restaurant->id]))->test(PrintScreen::class);

        $this->assertSame(['KLCC DELI'], $c->viewData('printers')->pluck('name')->all());
        $this->assertSame(['WAITER WONG'], $c->viewData('employees')->pluck('name')->all());
    }

    /** A user across two outlets sees both, because they work in both. */
    public function test_a_multi_outlet_user_sees_every_printer_they_work_with(): void
    {
        $c = Livewire::actingAs($this->user([$this->restaurant->id, $this->kitchenOutlet->id]))
            ->test(PrintScreen::class);

        $this->assertSame(['CK LINE 1', 'KLCC DELI'],
            $c->viewData('printers')->pluck('name')->sort()->values()->all());
    }

    // ── A dropdown is not a control ───────────────────────────────────────

    /** Printing is physical: a forged printer id must not reach another room. */
    public function test_printing_on_a_printer_that_was_never_offered_is_refused(): void
    {
        Livewire::actingAs($this->kitchenUser())->test(PrintScreen::class)
            ->set('printerId', $this->restaurantPrinter->id)
            ->set('employeeId', $this->chef->id)
            ->call('addItem', 'custom', 0)
            ->set('tray', [[
                'type' => 'custom', 'id' => 0, 'name' => 'Sauce',
                'label_type' => 'prep', 'storage_state' => 'chilled', 'copies' => 1, 'end_at' => null,
            ]])
            ->call('printTray')
            ->assertHasErrors('tray');

        $this->assertDatabaseCount('label_prints', 0);
    }

    /** And the audit trail must not be signed in someone else's name. */
    public function test_naming_a_preparer_from_another_outlet_is_refused(): void
    {
        Livewire::actingAs($this->kitchenUser())->test(PrintScreen::class)
            ->set('printerId', $this->kitchenPrinter->id)
            ->set('employeeId', $this->waiter->id)
            ->set('tray', [[
                'type' => 'custom', 'id' => 0, 'name' => 'Sauce',
                'label_type' => 'prep', 'storage_state' => 'chilled', 'copies' => 1, 'end_at' => null,
            ]])
            ->call('printTray')
            ->assertHasErrors('tray');

        $this->assertDatabaseCount('label_prints', 0);
    }
}
