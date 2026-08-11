<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Livewire\Hr\Employees;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Employees list keeps its filters, and "Resigned" shows the resigned.
 *
 * TWO REPORTED FAULTS, both found while maintaining outsourced staff.
 *
 *  1. Saving an employee returned to a list showing EVERYBODY. Only the outlet
 *     was carried through the form; section, employment and active were
 *     dropped. It was worse to spot than losing the outlet, because the record
 *     just edited was still in the list — so it read as the filter having been
 *     ignored rather than lost.
 *
 *  2. Choosing "Resigned" showed almost nobody unless "Inactive" was ALSO
 *     chosen. A resignation whose date has passed sets the employee inactive,
 *     so Resigned and the default Active are very nearly contradictory: the
 *     only people in both are those working out their notice.
 */
class EmployeeListFilterRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Filter Co', 'slug' => Str::slug('Filter Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->kitchen = Section::create([
            'company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.employees.manage', 'hr.employment'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage', 'hr.employment']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(string $name, ?string $status, bool $active = true, ?string $date = null): Employee
    {
        // Probation, confirmation and resignation all REQUIRE their date — the
        // form refuses to save without one — so a fixture without it cannot be
        // put back through the form this test drives.
        if ($date === null && array_key_exists($status, Employee::EMPLOYMENT_STATUS_DATE_LABELS)) {
            $date = '2025-06-01';
        }

        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => $active, 'join_date' => '2025-01-01',
            'employment_status' => $status, 'employment_status_date' => $date,
            'outsourcing_company' => $status === 'outsourcing' ? 'Experiva' : null,
        ]);
    }

    /** @return array<int, string> */
    private function listed(\Livewire\Features\SupportTesting\Testable $c): array
    {
        return $c->viewData('employees')->getCollection()->pluck('name')->sort()->values()->all();
    }

    // ── 1. The filters survive the round trip ─────────────────────────────

    public function test_saving_an_employee_returns_to_the_filtered_list(): void
    {
        $agency = $this->employee('BBB AGENCY', 'outsourcing');
        $this->employee('AAA CONFIRMED', 'confirmed');

        $form = Livewire::actingAs($this->user)->test(EmployeeForm::class, [
            'id' => $agency->id, 'outlet' => (string) $this->outlet->id,
            'section' => 'all', 'employment' => 'outsourcing', 'status' => 'all',
        ]);

        $form->call('save')->assertHasNoErrors();

        $redirect = $form->effects['redirect'] ?? '';
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $this->assertSame('outsourcing', $query['employment'] ?? null,
            'The employment filter must survive the save — the reported bug.');

        $landed = Livewire::actingAs($this->user)->test(Employees::class, $query);

        $this->assertSame('outsourcing', $landed->get('employmentStatusFilter'));
        $this->assertSame(['BBB AGENCY'], $this->listed($landed),
            'Landing on a list of everybody is what was reported.');
    }

    public function test_the_section_filter_survives_too(): void
    {
        $inKitchen = $this->employee('KITCHEN HAND', 'confirmed');
        $inKitchen->update(['section_id' => $this->kitchen->id]);
        $this->employee('FLOOR STAFF', 'confirmed');

        $form = Livewire::actingAs($this->user)->test(EmployeeForm::class, [
            'id' => $inKitchen->id, 'outlet' => (string) $this->outlet->id,
            'section' => (string) $this->kitchen->id, 'employment' => 'all', 'status' => 'all',
        ]);
        $form->call('save')->assertHasNoErrors();

        parse_str((string) parse_url($form->effects['redirect'] ?? '', PHP_URL_QUERY), $query);
        $landed = Livewire::actingAs($this->user)->test(Employees::class, $query);

        $this->assertSame(['KITCHEN HAND'], $this->listed($landed));
    }

    /** "All" is a choice, and must not be read back as "no choice". */
    public function test_a_deliberately_widened_list_stays_widened(): void
    {
        $emp = $this->employee('SOMEBODY', 'confirmed');
        $this->employee('ELSEWHERE', 'outsourcing');

        $form = Livewire::actingAs($this->user)->test(EmployeeForm::class, [
            'id' => $emp->id, 'outlet' => 'all', 'section' => 'all',
            'employment' => 'all', 'status' => 'all',
        ]);
        $form->call('save')->assertHasNoErrors();

        parse_str((string) parse_url($form->effects['redirect'] ?? '', PHP_URL_QUERY), $query);
        $landed = Livewire::actingAs($this->user)->test(Employees::class, $query);

        $this->assertSame('', $landed->get('outletFilter'));
        $this->assertSame('', $landed->get('employmentStatusFilter'));
        $this->assertSame('', $landed->get('statusFilter'));
        $this->assertSame(['ELSEWHERE', 'SOMEBODY'], $this->listed($landed));
    }

    /**
     * A form reached directly — a bookmark, a link in an email — has no list
     * to go back to, so it falls back to the employee's own outlet rather than
     * inventing a filter nobody chose.
     */
    public function test_a_form_opened_directly_falls_back_to_the_employees_outlet(): void
    {
        $emp = $this->employee('SOMEBODY', 'confirmed');

        $form = Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $emp->id]);

        $this->assertSame([], $form->instance()->returnParams());

        $form->call('save')->assertHasNoErrors();

        $this->assertStringContainsString(
            'outlet=' . $this->outlet->id,
            (string) ($form->effects['redirect'] ?? '')
        );
    }

    // ── 2. Resigned actually shows the resigned ───────────────────────────

    public function test_choosing_resigned_shows_the_resigned_without_a_second_dropdown(): void
    {
        $this->employee('AAA CONFIRMED', 'confirmed');
        // Left already — the nightly job set them inactive.
        $this->employee('CCC LEFT', 'resigned', false, '2026-01-31');
        // Working out their notice — still active.
        $this->employee('DDD LEAVING', 'resigned', true, '2026-12-31');

        $c = Livewire::actingAs($this->user)->test(Employees::class, ['outlet' => (string) $this->outlet->id])
            ->set('employmentStatusFilter', 'resigned');

        $this->assertSame(['CCC LEFT', 'DDD LEAVING'], $this->listed($c),
            'Someone who has actually left must appear without also picking Inactive.');
        $this->assertSame('', $c->get('statusFilter'),
            'The widening happens on the control, so the list matches what the screen says.');
    }

    /** A choice already made is not overwritten. */
    public function test_a_deliberate_active_choice_is_respected(): void
    {
        $this->employee('CCC LEFT', 'resigned', false, '2026-01-31');
        $this->employee('DDD LEAVING', 'resigned', true, '2026-12-31');

        $c = Livewire::actingAs($this->user)->test(Employees::class, ['outlet' => (string) $this->outlet->id])
            ->set('statusFilter', 'inactive')
            ->set('employmentStatusFilter', 'resigned');

        $this->assertSame('inactive', $c->get('statusFilter'));
        $this->assertSame(['CCC LEFT'], $this->listed($c));
    }

    /** Every other employment status describes current staff — leave Active alone. */
    public function test_other_employment_filters_do_not_widen_the_status(): void
    {
        $this->employee('BBB AGENCY', 'outsourcing');
        $this->employee('OLD AGENCY', 'outsourcing', false);

        $c = Livewire::actingAs($this->user)->test(Employees::class, ['outlet' => (string) $this->outlet->id])
            ->set('employmentStatusFilter', 'outsourcing');

        $this->assertSame('active', $c->get('statusFilter'));
        $this->assertSame(['BBB AGENCY'], $this->listed($c));
    }
}
