<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\PayrollRun;
use App\Models\StatutorySetting;
use App\Models\User;
use App\Services\Payroll\PayrollRunBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The payroll run as a spreadsheet, available on a DRAFT.
 *
 * ASKED FOR: download the payroll draft in Excel. The statutory and bank
 * exports are approved-only and rightly so — they commit the company to a
 * figure — but this is the working copy somebody checks BEFORE approving, the
 * same call the run list PDF already makes.
 *
 * What that buys has to be paid for in a warning, so the draft banner and the
 * DRAFT filename are asserted as features rather than decoration: a sheet of
 * payroll figures with no status on it is indistinguishable from the final
 * one once it has been emailed on.
 */
class PayrollRunExcelTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;
    private User $user;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Sheet Co', 'slug' => Str::slug('Sheet Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true, 'pcb_enabled' => false])->save();

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AAA STAFF', 'staff_id' => 'E001', 'is_active' => true,
            'join_date' => '2025-01-01', 'date_of_birth' => '1990-01-01',
            'basic_salary' => 3000, 'pay_type' => 'monthly',
            'ic_number' => '900101015511',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.payroll', 'hr.payroll.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.payroll', 'hr.payroll.approve']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->month = Carbon::parse('2026-07-01');
    }

    private function build(): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->month, $this->outlet->id, $this->user->id,
        );
    }

    /** @return array{sheet: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, filename: string} */
    private function download(PayrollRun $run, ?User $as = null): array
    {
        $response = $this->actingAs($as ?: $this->user)
            ->get(route('hr.payroll.run-excel', $run))
            ->assertOk();

        return [
            'sheet' => IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet(),
            'filename' => $response->headers->get('content-disposition') ?? '',
        ];
    }

    /** @return array<string, string> heading => value on the first data row. */
    private function firstRow(PayrollRun $run): array
    {
        $sheet = $this->download($run)['sheet'];

        $row = [];
        foreach ($sheet->getRowIterator(4, 4)->current()->getCellIterator() as $cell) {
            $heading = trim((string) $cell->getValue());
            if ($heading === '') {
                continue;
            }
            $row[$heading] = (string) $sheet->getCell($cell->getColumn() . '5')->getValue();
        }

        return $row;
    }

    public function test_a_draft_can_be_downloaded(): void
    {
        $run = $this->build();
        $this->assertTrue($run->isEditable(), 'The fixture must be a draft for this to mean anything.');

        $row = $this->firstRow($run);

        $this->assertSame('AAA STAFF', $row['Employee']);
        $this->assertSame('E001', $row['Staff ID']);
        $this->assertSame('3000', $row['Basic']);
    }

    /**
     * The banner is the price of offering this before approval.
     *
     * It sits ABOVE the headings, in the file — a caveat that lives only on
     * the screen the file came from is gone the moment it is emailed on.
     */
    public function test_a_draft_carries_its_warning_in_the_file(): void
    {
        $sheet = $this->download($this->build())['sheet'];

        $this->assertStringContainsString('DRAFT', (string) $sheet->getCell('A3')->getValue());
        $this->assertStringContainsString('can still change', (string) $sheet->getCell('A3')->getValue());
    }

    /** And in the filename, so two exports of one run do not collide. */
    public function test_the_draft_filename_says_so(): void
    {
        $this->assertStringContainsString('-DRAFT.xlsx', $this->download($this->build())['filename']);
    }

    /**
     * Approving removes the banner rather than rewording it — a warning that
     * is always present is one nobody reads.
     */
    public function test_an_approved_run_has_no_draft_banner(): void
    {
        $run = $this->build();
        $run->update([
            'status' => PayrollRun::APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $file = $this->download($run->fresh());

        $this->assertSame('', trim((string) $file['sheet']->getCell('A3')->getValue()));
        $this->assertStringNotContainsString('DRAFT', $file['filename']);

        // The headings must still be on row 4 — the banner is omitted, not
        // collapsed, or every row reference below it shifts.
        $this->assertSame('No.', (string) $file['sheet']->getCell('A4')->getValue());
    }

    /**
     * An account number is digits, not a quantity.
     *
     * Written as a number, "0123456789" comes back 123456789 — wrong in a way
     * nobody notices until a transfer bounces. Same for an IC.
     */
    public function test_identifiers_keep_their_leading_zeros(): void
    {
        $row = $this->firstRow($this->build());

        $this->assertSame('0123456789', $row['Account No.']);
        $this->assertSame('900101015511', $row['IC No.']);
    }

    /**
     * The totals row re-totals what a filter leaves, rather than restating a
     * figure computed at export time.
     */
    public function test_the_totals_row_is_a_live_subtotal(): void
    {
        $sheet = $this->download($this->build())['sheet'];

        // One employee, so the total row is row 6.
        $this->assertSame('TOTAL', (string) $sheet->getCell('A6')->getValue());

        $formulae = [];
        foreach ($sheet->getRowIterator(6, 6)->current()->getCellIterator() as $cell) {
            $v = (string) $cell->getValue();
            if (str_starts_with($v, '=')) {
                $formulae[] = $v;
            }
        }

        $this->assertNotEmpty($formulae, 'The money columns must total.');
        foreach ($formulae as $f) {
            $this->assertStringStartsWith('=SUBTOTAL(9,', $f,
                'SUM under a filter states the total of rows that are no longer on screen.');
        }
    }

    /** A column that would be a stripe of zeroes is left out, not shown empty. */
    public function test_service_charge_and_adjustment_columns_are_omitted_when_unused(): void
    {
        $row = $this->firstRow($this->build());

        $this->assertArrayNotHasKey('Service Charge', $row);
        $this->assertArrayNotHasKey('Adjustments', $row);

        // The columns either side of where they would have sat are present,
        // so this is not passing because the sheet is empty.
        $this->assertArrayHasKey('OT Amount', $row);
        $this->assertArrayHasKey('Gross', $row);
    }

    /** An adjustment on the run brings its column back. */
    public function test_the_adjustment_column_appears_once_there_is_one(): void
    {
        $run = $this->build();

        \App\Models\PayrollRunAdjustment::create([
            'company_id' => $this->company->id, 'payroll_run_id' => $run->id,
            'employee_id' => $this->employee->id, 'label' => 'Advance recovery',
            'amount' => 150, 'direction' => \App\Models\PayrollRunAdjustment::DEDUCTION,
            'affects_statutory' => false, 'created_by' => $this->user->id,
        ]);

        // Rebuilt, because adjustments are an INPUT to the build.
        $run = $this->build();

        $row = $this->firstRow($run);

        $this->assertArrayHasKey('Adjustments', $row);
        $this->assertSame('-150', $row['Adjustments']);
    }

    public function test_it_is_refused_without_the_payroll_ability(): void
    {
        $run = $this->build();

        $other = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $other->companies()->syncWithoutDetaching([$this->company->id]);
        $other->outlets()->sync([$this->outlet->id]);

        $this->actingAs($other)
            ->get(route('hr.payroll.run-excel', $run))
            ->assertForbidden();
    }

    /** A run for a branch the viewer cannot see is refused, not merely empty. */
    public function test_a_run_for_an_inaccessible_outlet_is_refused(): void
    {
        $elsewhere = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'IOI', 'code' => 'IOI', 'is_active' => true,
        ]);

        $run = $this->build();
        $run->update(['outlet_id' => $elsewhere->id]);

        $narrow = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $narrow->companies()->syncWithoutDetaching([$this->company->id]);
        $narrow->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $narrow->givePermissionTo('hr.payroll');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($narrow)
            ->get(route('hr.payroll.run-excel', $run->fresh()))
            ->assertForbidden();
    }
}
