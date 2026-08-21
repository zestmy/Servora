<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Models\CentralKitchen;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Service charge paid from" must never make an employee unsaveable.
 *
 * Reported from a Central Kitchen: adding and editing kitchen staff failed on
 * "The selected f service charge outlet id is invalid", a field the user could
 * not see and had not touched.
 *
 * The picker only ever listed outlets the CURRENT USER can reach, but the
 * property was hydrated straight from the record. Kitchen users are attached
 * to the kitchen's base outlet alone — see KitchenManagement — so anybody
 * carrying a redirect to a shop outlet came back with a value that had no
 * matching <option>. The select rendered as "Their own outlet" while the
 * property still held the old id, so the field looked correct, the rule
 * refused it, and there was nothing on screen to change. Without
 * hr.compensation the field is not even rendered, so the error named a tab the
 * user did not have.
 *
 * The same dead end opened for a full-access user whenever the receiving
 * outlet was closed: outlets soft delete, so the FK's nullOnDelete never
 * fires and the id survives on a row the picker no longer lists.
 */
class ServiceChargeOutletPickerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $shop;
    private Outlet $kitchenOutlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Dotty Co', 'slug' => Str::slug('Dotty Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->shop = Outlet::create([
            'company_id' => $this->company->id, 'name' => "DOTTY'S - BANGSAR",
            'code' => 'DTYBGS', 'is_active' => true,
        ]);

        $this->kitchenOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => "DOTTY'S - CENTRAL KITCHEN",
            'code' => 'DTYCK', 'is_active' => true,
        ]);

        CentralKitchen::create([
            'company_id' => $this->company->id, 'name' => "DOTTY'S - CENTRAL KITCHEN",
            'code' => 'CK1', 'outlet_id' => $this->kitchenOutlet->id, 'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.employees.manage', 'hr.compensation', 'hr.employment'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * @param array<int, int>          $outletIds
     * @param array<int, string>|null  $abilities
     */
    private function user(array $outletIds, bool $viewAll = false, ?array $abilities = null): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => $viewAll,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync($outletIds);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities ?? ['hr.view', 'hr.employees.manage', 'hr.compensation', 'hr.employment']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** A kitchen employee already paid from a shop pool the kitchen user cannot see. */
    private function redirectedEmployee(?int $paidFrom = null): Employee
    {
        return Employee::create([
            'company_id'               => $this->company->id,
            'outlet_id'                => $this->kitchenOutlet->id,
            'service_charge_outlet_id' => $paidFrom ?? $this->shop->id,
            'name'                     => 'ADAM SHAFIQ CHIN BIN FIRDAUS',
            'is_active'                => true,
        ]);
    }

    /** The report, as a test: an unrelated edit went through and did not. */
    public function test_an_unreachable_redirect_does_not_block_an_unrelated_edit(): void
    {
        $emp = $this->redirectedEmployee();

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_staff_id', 'DTY0058')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('DTY0058', $emp->fresh()->staff_id);
    }

    /** And the redirect it could not show is left exactly as it was. */
    public function test_an_unreachable_redirect_survives_that_edit(): void
    {
        $emp = $this->redirectedEmployee();

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_staff_id', 'DTY0058')
            ->call('save');

        $this->assertSame($this->shop->id, $emp->fresh()->service_charge_outlet_id);
    }

    /**
     * Visible, not merely tolerated. Somebody has to be able to see what the
     * record is set to before they can decide to change it.
     */
    public function test_the_picker_lists_the_outlet_the_record_already_points_at(): void
    {
        $emp = $this->redirectedEmployee();

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->assertSee("DOTTY'S - BANGSAR");
    }

    /** Clearing it is the whole point of showing it — blank must save. */
    public function test_an_unreachable_redirect_can_be_cleared(): void
    {
        $emp = $this->redirectedEmployee();

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_service_charge_outlet_id', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($emp->fresh()->service_charge_outlet_id);
    }

    /**
     * The other way in, with no access limits involved at all: outlets soft
     * delete, so closing one leaves the id on the row.
     */
    public function test_a_closed_outlet_does_not_make_the_record_unsaveable(): void
    {
        $closed = Outlet::create([
            'company_id' => $this->company->id, 'name' => "DOTTY'S - MIDVALLEY",
            'code' => 'DTYMV', 'is_active' => true,
        ]);
        $emp = $this->redirectedEmployee($closed->id);
        $closed->delete();

        Livewire::actingAs($this->user([$this->shop->id, $this->kitchenOutlet->id], viewAll: true))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_staff_id', 'DTY0058')
            ->call('save')
            ->assertHasNoErrors();
    }

    /** Listed, but marked — a removed outlet is not a live choice. */
    public function test_a_closed_outlet_is_named_as_removed(): void
    {
        $closed = Outlet::create([
            'company_id' => $this->company->id, 'name' => "DOTTY'S - MIDVALLEY",
            'code' => 'DTYMV', 'is_active' => true,
        ]);
        $emp = $this->redirectedEmployee($closed->id);
        $closed->delete();

        Livewire::actingAs($this->user([$this->shop->id, $this->kitchenOutlet->id], viewAll: true))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->assertSee("DOTTY'S - MIDVALLEY (removed)");
    }

    /**
     * The field sits behind hr.compensation, so a save without it leaves the
     * pool alone rather than writing back a value the picker never showed —
     * the same rule salary and service points already follow.
     */
    public function test_a_save_without_compensation_leaves_the_pool_alone(): void
    {
        $emp = $this->redirectedEmployee();

        Livewire::actingAs($this->user(
            [$this->kitchenOutlet->id],
            abilities: ['hr.view', 'hr.employees.manage', 'hr.employment'],
        ))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_staff_id', 'DTY0058')
            ->set('f_service_charge_outlet_id', $this->kitchenOutlet->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->shop->id, $emp->fresh()->service_charge_outlet_id);
    }

    /**
     * The guard the rule exists for still holds: this widens the list by the
     * one value already recorded, not by every outlet in the company.
     */
    public function test_money_still_cannot_be_moved_into_an_outlet_the_user_cannot_see(): void
    {
        $emp = $this->redirectedEmployee(paidFrom: $this->kitchenOutlet->id);

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_service_charge_outlet_id', $this->shop->id)
            ->call('save')
            ->assertHasErrors('f_service_charge_outlet_id');
    }

    /** The message names the field on screen, not the property behind it. */
    public function test_the_refusal_names_the_field_a_user_would_recognise(): void
    {
        $emp = $this->redirectedEmployee(paidFrom: $this->kitchenOutlet->id);

        $errors = Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_service_charge_outlet_id', $this->shop->id)
            ->call('save')
            ->errors();

        $this->assertStringContainsString(
            'Service charge paid from',
            $errors->first('f_service_charge_outlet_id'),
        );
    }

    /**
     * The widening is server-side only. The remembered value is what lets an
     * unreachable outlet through the rule, so a payload that could set it
     * would be a way to redirect anybody's service charge into a branch the
     * sender cannot see.
     */
    public function test_the_remembered_outlet_cannot_be_set_from_the_browser(): void
    {
        $emp = $this->redirectedEmployee(paidFrom: $this->kitchenOutlet->id);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('originalServiceChargeOutletId', $this->shop->id);
    }

    /** Adding kitchen staff, which is where this was reported. */
    public function test_a_kitchen_user_can_add_an_employee_to_the_kitchen(): void
    {
        Livewire::actingAs($this->user([$this->kitchenOutlet->id]))
            ->test(EmployeeForm::class)
            ->set('f_outlet_id', $this->kitchenOutlet->id)
            ->set('f_name', 'ADAM SHAFIQ CHIN BIN FIRDAUS')
            ->set('f_staff_id', 'DTY0058')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'staff_id'                 => 'DTY0058',
            'outlet_id'                => $this->kitchenOutlet->id,
            'service_charge_outlet_id' => null,
        ]);
    }
}
