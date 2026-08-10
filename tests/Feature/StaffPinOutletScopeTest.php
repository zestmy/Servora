<?php

namespace Tests\Feature;

use App\Livewire\Hr\StaffPins;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A PIN is issued for a branch you actually run.
 *
 * Staff PINs listed every active employee in the company and its writes were
 * scoped by CompanyScope alone, so a manager of one branch could issue or revoke
 * a PIN for staff across town — no forged request needed, just a scroll. A PIN
 * opens clock-in as well as label printing, so that is handing out attendance
 * for a shift you do not run.
 *
 * HR > Employees has always scoped both its list and its writes by
 * accessibleOutletIds(); this screen was the outlier. The two cases worth
 * pinning are that the list narrows AND that a posted id from outside the scope
 * is refused — hiding a row is a courtesy, the guard is the boundary.
 */
class StaffPinOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $mine;
    private Outlet $theirs;
    private Employee $myStaff;
    private Employee $theirStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Pin Scope Co',
            'slug'      => Str::slug('Pin Scope Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->mine   = $this->outlet('Mine', 'MINE');
        $this->theirs = $this->outlet('Theirs', 'THRS');

        $this->myStaff    = $this->employee('AISYAH', $this->mine);
        $this->theirStaff = $this->employee('BALQIS', $this->theirs);
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id,
            'name'       => $name,
            'code'       => $code,
            'is_active'  => true,
        ]);
    }

    private function employee(string $name, Outlet $outlet): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $outlet->id,
            'name'       => $name,
            'is_active'  => true,
        ]);
    }

    /** @param array<int, Outlet> $outlets */
    private function manager(array $outlets, bool $allOutlets = false): User
    {
        $user = User::factory()->create([
            'company_id'           => $this->company->id,
            'can_view_all_outlets' => $allOutlets,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    public function test_the_list_shows_only_staff_of_branches_you_run(): void
    {
        $html = Livewire::actingAs($this->manager([$this->mine]))->test(StaffPins::class)->html();

        $this->assertStringContainsString('AISYAH', $html);
        $this->assertStringNotContainsString(
            'BALQIS',
            $html,
            'A manager of one branch was able to scroll to another branch\'s staff.'
        );
    }

    public function test_multi_outlet_access_sees_both(): void
    {
        $html = Livewire::actingAs($this->manager([$this->mine, $this->theirs]))->test(StaffPins::class)->html();

        $this->assertStringContainsString('AISYAH', $html);
        $this->assertStringContainsString('BALQIS', $html);
    }

    public function test_an_all_outlets_account_sees_both_without_being_attached(): void
    {
        $html = Livewire::actingAs($this->manager([], allOutlets: true))->test(StaffPins::class)->html();

        $this->assertStringContainsString('AISYAH', $html);
        $this->assertStringContainsString('BALQIS', $html);
    }

    /**
     * The row being hidden is a courtesy. This is the boundary: the id is posted
     * straight to the action, exactly as a forged Livewire payload would.
     */
    public function test_issuing_a_pin_outside_your_scope_is_refused(): void
    {
        Livewire::actingAs($this->manager([$this->mine]))
            ->test(StaffPins::class)
            ->call('issuePin', $this->theirStaff->id)
            ->assertForbidden();

        $this->assertNull($this->theirStaff->fresh()->label_pin);
    }

    public function test_revoking_a_pin_outside_your_scope_is_refused(): void
    {
        $this->theirStaff->setLabelPin('4821');

        Livewire::actingAs($this->manager([$this->mine]))
            ->test(StaffPins::class)
            ->call('revoke', $this->theirStaff->id)
            ->assertForbidden();

        $this->assertNotNull(
            $this->theirStaff->fresh()->label_pin,
            'Someone else\'s staff must still be able to clock in.'
        );
    }

    public function test_setting_a_manual_pin_outside_your_scope_is_refused(): void
    {
        Livewire::actingAs($this->manager([$this->mine]))
            ->test(StaffPins::class)
            ->call('openManual', $this->theirStaff->id)
            ->assertForbidden();
    }

    public function test_your_own_staff_can_still_be_given_a_pin(): void
    {
        Livewire::actingAs($this->manager([$this->mine]))
            ->test(StaffPins::class)
            ->call('issuePin', $this->myStaff->id)
            ->assertHasNoErrors();

        $this->assertNotNull($this->myStaff->fresh()->label_pin);
    }
}
