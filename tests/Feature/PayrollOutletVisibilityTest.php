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
 * NOTHING was scoped except two of the exports. The list paginated every run
 * in the company, so another branch's gross, net and headcount were readable
 * from the index; the run screen fetched by uuid, the payslip PDFs fetched by
 * run id, and the statutory submission and bank payment files each checked
 * the permission and the company and stopped there. Only the Excel and
 * list-PDF exports asked about the outlet — each with its own copy of the
 * question, which is how the rest came to be missed.
 *
 * A COMPANY-WIDE run (no outlet) needs access to the WHOLE company. It pays
 * every branch, so no single outlet answers the question; asking for all of
 * them rather than refusing outright is what keeps it readable where it
 * should be, since a single-outlet company's manager holds every outlet
 * there is. See User::coversEveryOutlet().
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

    /** It pays every branch, so one branch's manager may not read it. */
    public function test_a_company_wide_run_is_hidden_from_a_restricted_user(): void
    {
        $run = $this->makeRun(null);

        Livewire::actingAs($this->manager())
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertForbidden();
    }

    /**
     * And is readable by somebody who covers the whole company, whether by
     * the view-all flag or by simply holding every outlet — which is what
     * stops this rule locking the only run a small company ever makes.
     */
    public function test_a_company_wide_run_opens_for_somebody_who_covers_every_outlet(): void
    {
        $run = $this->makeRun(null);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->mine->id, $this->theirs->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo(['hr.view', 'hr.payroll']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::actingAs($user)
            ->test(PayrollRunShow::class, ['run' => $run->uuid])
            ->assertOk();
    }

    /** The list is the other way in, and it had no filter at all. */
    public function test_the_list_shows_only_runs_the_user_may_open(): void
    {
        $mine    = $this->makeRun($this->mine);
        $theirs  = $this->makeRun($this->theirs);
        $company = $this->makeRun(null);

        $html = Livewire::actingAs($this->manager())
            ->test(\App\Livewire\Hr\Payroll::class)
            ->html();

        $this->assertStringContainsString($mine->uuid, $html);
        $this->assertStringNotContainsString($theirs->uuid, $html);
        $this->assertStringNotContainsString($company->uuid, $html);
    }

    /** Generating a company-wide run needs the same reach as reading one. */
    public function test_a_restricted_user_cannot_generate_a_company_wide_run(): void
    {
        Livewire::actingAs($this->manager())
            ->test(\App\Livewire\Hr\Payroll::class)
            ->set('newMonth', '2026-08')
            ->set('newOutlet', '')
            ->call('generate')
            ->assertHasErrors('newOutlet');
    }
}
