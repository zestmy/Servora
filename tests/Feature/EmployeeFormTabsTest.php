<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
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
 * Which side of the pay wall each field of the employee form sits on.
 *
 * Moved on 2026-08-11 at Affandy's request, in both directions:
 *
 *   TO the restricted tab — clock in on own phone, clock in from anywhere,
 *   overtime settled as, service charge paid from. Each decides what somebody
 *   is PAID or where it comes from: two waive a control on the punch that
 *   becomes their hours, one sends approved overtime to a balance instead of a
 *   payslip, and the last moves a person between service charge pools.
 *
 *   OUT of it — bank, account number, account name. Where a salary is paid is
 *   not what it is, and the people who keep staff records current were having
 *   to be shown the whole payroll to fix an account number.
 *
 * The second direction WIDENS who can see an account number and a holder's
 * name that is often a family member's. That was the instruction and it is
 * recorded here, in the test that would fail if somebody quietly put it back.
 */
class EmployeeFormTabsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Tabs Co', 'slug' => Str::slug('Tabs Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH BINTI RAHMAN',
            'basic_salary' => 4321,
            'bank_name' => 'MAYBANK', 'bank_account_no' => '1234567890',
            'allow_anywhere' => true, 'overtime_as_time_off' => true,
            'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.employees.manage', 'hr.compensation', 'hr.employment'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /** @param array<int, string> $abilities */
    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function form(array $abilities)
    {
        return Livewire::actingAs($this->user($abilities))
            ->test(EmployeeForm::class, ['id' => $this->employee->id]);
    }

    /** The requested widening, stated as a test. */
    public function test_banking_is_visible_without_salary_access(): void
    {
        $form = $this->form(['hr.view', 'hr.employees.manage']);

        $form->assertSet('f_bank_name', 'MAYBANK')
            ->assertSet('f_bank_account_no', '1234567890')
            ->assertSee('Bank Account No.');
    }

    /** And editable, which is the point of moving it. */
    public function test_banking_can_be_changed_without_salary_access(): void
    {
        $this->form(['hr.view', 'hr.employees.manage'])
            ->set('f_bank_account_no', '9988776655')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('9988776655', $this->employee->fresh()->bank_account_no);
    }

    /** Salary itself did NOT move. */
    public function test_salary_is_still_hidden_without_compensation(): void
    {
        $html = $this->form(['hr.view', 'hr.employees.manage'])->html();

        $this->assertStringNotContainsString('4,321', $html);
        $this->assertStringNotContainsString('4321', $html);
    }

    /** Saving as an ordinary HR user must not blank the salary it cannot see. */
    public function test_saving_without_compensation_leaves_the_salary_alone(): void
    {
        $this->form(['hr.view', 'hr.employees.manage'])
            ->set('f_designation', 'Sous Chef')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsWithDelta(4321, (float) $this->employee->fresh()->basic_salary, 0.01);
    }

    /**
     * The four that moved the other way are gone from the form for anyone
     * without hr.compensation.
     */
    public function test_the_moved_settings_are_behind_the_pay_wall(): void
    {
        $html = $this->form(['hr.view', 'hr.employees.manage'])->html();

        foreach (['Clock in on own phone', 'Clock in from anywhere',
                  'Overtime settled as', 'Service charge paid from'] as $label) {
            $this->assertStringNotContainsString($label, $html, "\"$label\" should now be restricted.");
        }
    }

    /** And present for somebody who holds it. */
    public function test_the_moved_settings_are_there_with_compensation(): void
    {
        $html = $this->form(['hr.view', 'hr.employees.manage', 'hr.compensation'])->html();

        foreach (['Clock in on own phone', 'Clock in from anywhere',
                  'Overtime settled as', 'Service charge paid from'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    /**
     * Every tab closes before the next one opens.
     *
     * Moving the banking fields between tabs took the closing tag of the grid
     * they lived in with them, so the Compensation wrapper never closed and
     * Statutory, Certifications, Documents and Activity ended up nested INSIDE
     * it — hidden by its own x-show, and blank on screen. Nothing failed: the
     * page rendered, Livewire saw one root element, and every field test still
     * passed, because the markup was present and merely unreachable.
     *
     * This counts the divs between one tab wrapper and the next. Crude, and it
     * is the one thing that would have caught it.
     */
    public function test_each_tab_closes_before_the_next_one_opens(): void
    {
        $html = $this->form(['hr.view', 'hr.employees.manage', 'hr.compensation'])->html();

        $tabs = ['personal', 'employment', 'pay', 'statutory', 'compliance', 'documents', 'activity'];

        $positions = [];

        foreach ($tabs as $tab) {
            // x-show, specifically: the tab BUTTONS carry "tab === 'pay'"
            // too, and matching those compares slices of the tab bar with
            // each other — which balances no matter how broken the panels
            // below are. This test passed against the actual bug until it
            // looked for the panel.
            $at = strpos($html, "x-show=\"tab === '{$tab}'\"");

            if ($at !== false) {
                $positions[$tab] = $at;
            }
        }

        $this->assertGreaterThan(4, count($positions), 'The form should render most of its tabs here.');

        $keys = array_keys($positions);

        foreach ($keys as $i => $tab) {
            // The last one runs to the end of the component, where the outer
            // wrapper closes too, so it is not comparable.
            if (! isset($keys[$i + 1])) {
                continue;
            }

            $slice = substr($html, $positions[$tab], $positions[$keys[$i + 1]] - $positions[$tab]);

            $this->assertSame(
                substr_count($slice, '<div'),
                substr_count($slice, '</div>'),
                "The '{$tab}' tab does not close before '{$keys[$i + 1]}' opens — "
                    . 'everything after it is nested inside and renders blank.'
            );
        }
    }

    /**
     * The hazard of moving a field out of sight: a save from the screen that
     * no longer shows it must not reset it.
     */
    public function test_saving_without_compensation_preserves_the_moved_settings(): void
    {
        $this->form(['hr.view', 'hr.employees.manage'])
            ->set('f_designation', 'Sous Chef')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $this->employee->fresh();

        $this->assertTrue((bool) $fresh->allow_anywhere, 'A hidden switch was reset by an unrelated save.');
        $this->assertTrue((bool) $fresh->overtime_as_time_off);
    }
}
