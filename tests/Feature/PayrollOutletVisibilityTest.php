<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunShow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A payroll run belongs to a branch, and so does the right to open it.
 *
 * REPORTED AS: payroll — draft and approved, and the payslips with it —
 * should only be viewable for the outlets a user is allowed.
 *
 * The LIST was already scoped. What was not: the run screen fetched by uuid,
 * the payslip PDFs fetched by run id, and the statutory submission and bank
 * payment files. Each checked the permission and the company and stopped
 * there, so a manager restricted to one branch could not see another branch's
 * run in the list and could open it by following a link or guessing a uuid —
 * then print every payslip on it. The Excel and list-PDF exports had the
 * check; nothing else did.
 *
 * A COMPANY-WIDE run (no outlet) stays visible to anyone with hr.payroll.
 * That was the existing rule in the two exports that already checked, and it
 * is kept on purpose rather than tightened as a side effect — see
 * PayrollRun::isWithinOutlets().
 */
class PayrollOutletVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $mine;
    private Outlet $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Visibility Co', 'slug' => Str::slug('Visibility Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->mine = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);
        $this->theirs = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.payroll', 'hr.payroll.approve'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function makeRun(?Outlet $outlet, string $status = PayrollRun::DRAFT): PayrollRun
    {
        $run = PayrollRun::create([
            'company_id'   => $this->company->id,
            'outlet_id'    => $outlet?->id,
            'period_month' => '2026-07-01',
            'period_start' => '2026-07-01',
            'period_end'   => '2026-07-31',
            'status'       => $status,
        ]);

        $employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => ($outlet ?? $this->theirs)->id,
            'name' => 'PAYEE ' . $run->id, 'is_active' => true, 'join_date' => '2025-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
        ]);

        PayrollRunLine::create([
            'company_id'     => $this->company->id,
            'payroll_run_id' => $run->id,
            'employee_id'    => $employee->id,
            'employee_name'  => $employee->name,
            'basic'          => 3000,
            'gross'          => 3000,
            'net'            => 3000,
        ]);

        return $run->fresh();
    }

    /** Restricted to KLCC only. */
    private function manager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->mine->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.view', 'hr.payroll', 'hr.payroll.approve']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_their_own_outlets_run_opens(): void
    {
        $run = $this->makeRun($this->mine);

        Livewire::actingAs($this->manager())
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertOk();
    }

    public function test_another_outlets_draft_run_cannot_be_opened(): void
    {
        $run = $this->makeRun($this->theirs);

        Livewire::actingAs($this->manager())
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertForbidden();
    }

    /** Approved is the one that carries the final figures. */
    public function test_another_outlets_approved_run_cannot_be_opened(): void
    {
        $run = $this->makeRun($this->theirs, PayrollRun::APPROVED);

        Livewire::actingAs($this->manager())
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertForbidden();
    }

    public function test_another_outlets_payslips_cannot_be_printed(): void
    {
        $run  = $this->makeRun($this->theirs, PayrollRun::APPROVED);
        $line = $run->lines()->first();

        $this->actingAs($this->manager())
            ->get(route('hr.payroll.payslips', $run))
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->get(route('hr.payroll.payslip', [$run, $line]))
            ->assertForbidden();
    }

    /** Their own payslips still print, or the guard has gone too far. */
    public function test_their_own_payslips_still_print(): void
    {
        $run  = $this->makeRun($this->mine, PayrollRun::APPROVED);
        $line = $run->lines()->first();

        $this->actingAs($this->manager())
            ->get(route('hr.payroll.payslip', [$run, $line]))
            ->assertOk();
    }

    /**
     * A company-wide run stays readable. Kept deliberately: it is the only run
     * a single-outlet company has, and narrowing it is a policy decision to
     * take on purpose rather than as fallout from closing the hole above.
     */
    public function test_a_company_wide_run_is_still_visible(): void
    {
        $run = $this->makeRun(null);

        Livewire::actingAs($this->manager())
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertOk();
    }
}
