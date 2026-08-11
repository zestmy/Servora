<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\User;
use App\Services\Payroll\PayslipPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A payslip prints for an employee whose name contains a slash.
 *
 * REPORTED AS a 500 viewing a payslip in payroll. The cause was the FILENAME,
 * not the document: the controller built one by hand, replacing spaces and
 * nothing else, and Symfony refuses to put "/" or "\" in a Content-Disposition
 * header — so the download threw before a byte was rendered.
 *
 * "JANANE A/P KANAPATHY" is not an exotic input here. A/L and A/P — anak
 * lelaki, anak perempuan — are how Malaysian Indian names carry the
 * patronymic, and three employees in production had never been able to have a
 * payslip printed. The staff portal's own copy always worked, because it went
 * through PayslipPdf::filename(); the payroll screen did not.
 */
class PayslipFilenameTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private PayrollRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Payslip Co', 'slug' => Str::slug('Payslip Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->run = PayrollRun::create([
            'company_id'   => $this->company->id,
            'period_month' => '2026-07-01',
            'period_start' => '2026-07-01',
            'period_end'   => '2026-07-31',
            'status'       => PayrollRun::APPROVED,
            'reference'    => 'PR-2026-07-0001',
            'rates_were_confirmed' => true,
        ]);

        Permission::findOrCreate('hr.payroll', 'web');
    }

    private function line(string $name): PayrollRunLine
    {
        return PayrollRunLine::create([
            'company_id'      => $this->company->id,
            'payroll_run_id'  => $this->run->id,
            'employee_name'   => $name,
            'basic'           => 3000,
            'gross'           => 3000,
            'net'             => 2650,
        ]);
    }

    private function payrollUser(): User
    {
        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo('hr.payroll');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** The reported bug, at the layer it was reported from. */
    public function test_a_payslip_prints_for_a_name_containing_a_slash(): void
    {
        $line = $this->line('JANANE A/P KANAPATHY');

        $response = $this->actingAs($this->payrollUser())
            ->get(route('hr.payroll.payslip', ['run' => $this->run, 'line' => $line]));

        $response->assertOk();

        $this->assertStringNotContainsString(
            '/',
            $this->filenameFrom($response->headers->get('content-disposition')),
            'A slash in the filename is what Symfony refuses, and what threw.'
        );
    }

    public function test_the_whole_run_prints(): void
    {
        $this->line('SASITHARAN A/L SAMYNATHAN');
        $this->line('AISYAH BINTI RAHMAN');

        $this->actingAs($this->payrollUser())
            ->get(route('hr.payroll.payslips', ['run' => $this->run]))
            ->assertOk();
    }

    /** Both halves of the name survive, so the file is still identifiable. */
    public function test_the_filename_keeps_the_name_readable(): void
    {
        $name = app(PayslipPdf::class)->filename($this->run, 'JANANE A/P KANAPATHY');

        $this->assertSame('payslip-janane-a-p-kanapathy-pr-2026-07-0001.pdf', $name);
    }

    /** A reference is interpolated too, and was never sanitised either. */
    public function test_a_slash_in_the_run_reference_is_handled(): void
    {
        $this->run->reference = 'PR/2026/07';

        $this->assertSame('payslips-pr-2026-07.pdf', app(PayslipPdf::class)->runFilename($this->run));
    }

    /** A name of nothing but punctuation must not produce "payslip--.pdf". */
    public function test_a_name_with_nothing_usable_falls_back(): void
    {
        $name = app(PayslipPdf::class)->filename($this->run, '///');

        $this->assertSame('payslip-employee-pr-2026-07-0001.pdf', $name);
    }

    private function filenameFrom(?string $disposition): string
    {
        if ($disposition === null) {
            return '';
        }

        preg_match('/filename="?([^";]+)"?/', $disposition, $m);

        return $m[1] ?? '';
    }
}
