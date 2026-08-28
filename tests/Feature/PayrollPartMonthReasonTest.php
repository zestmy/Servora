<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompensationSetting;
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
 * WHY a basic is short, not merely by how much.
 *
 * ASKED FOR: a run already showed "11 of 31 days" and nothing else, so anybody
 * checking it had to leave the sheet, open the employee and work out whether
 * they joined late, resigned, or something was wrong — and the third
 * possibility is the reason anybody is checking in the first place.
 *
 * SNAPSHOTTED onto the line rather than joined from the employee, like every
 * other identity field here: somebody who resigns in August and is re-hired in
 * November must not make August's run say they were employed throughout, and a
 * deleted employee would take the explanation with them.
 *
 * NULL UNLESS THE DATE FALLS INSIDE THE RUN'S OWN PERIOD. A join date two
 * years ago is not why this month is short, and storing it would put a "joined
 * on" against every employee in the company and make the column meaningless —
 * so null means "not a factor here", and a sheet drops the column when nobody
 * on the run has one.
 */
class PayrollPartMonthReasonTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Part Co', 'slug' => Str::slug('Part Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $s = StatutorySetting::forCompany($this->company->id);
        $s->company_id = $this->company->id;
        $s->fill(['epf_enabled' => true, 'socso_enabled' => true, 'eis_enabled' => true, 'pcb_enabled' => false])->save();

        $c = CompensationSetting::forCompany($this->company->id);
        $c->company_id = $this->company->id;
        $c->fill(['monthly_working_days' => 26, 'daily_working_hours' => 8])->save();

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

        // July 2026, a full calendar month.
        $this->month = Carbon::parse('2026-07-01');
    }

    private function employee(string $name, array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2025-01-01',
            'date_of_birth' => '1990-01-01', 'basic_salary' => 3100, 'pay_type' => 'monthly',
            'employment_status' => 'confirmed', 'employment_status_date' => '2025-06-01',
        ], $extra));
    }

    private function build(): PayrollRun
    {
        return app(PayrollRunBuilder::class)->generate(
            $this->company->id, [$this->outlet->id], $this->month, $this->outlet->id, $this->user->id,
        );
    }

    private function line(PayrollRun $run, Employee $e): \App\Models\PayrollRunLine
    {
        return $run->lines->firstWhere('employee_id', $e->id);
    }

    // ── The snapshot ──────────────────────────────────────────────────────

    public function test_a_joiner_carries_the_date_they_joined(): void
    {
        $joiner = $this->employee('BBB JOINER', ['join_date' => '2026-07-17']);

        $line = $this->line($this->build(), $joiner);

        $this->assertSame('2026-07-17', $line->joined_on?->toDateString());
        $this->assertNull($line->resigned_on);
        $this->assertSame('joined 17 Jul 2026', $line->employmentNote());

        // And the figure it explains is genuinely short.
        $this->assertTrue($line->isProrated());
        $this->assertLessThan(3100.0, (float) $line->basic);
    }

    public function test_a_leaver_carries_the_date_they_resigned(): void
    {
        $leaver = $this->employee('CCC LEAVER', [
            'employment_status' => 'resigned', 'employment_status_date' => '2026-07-11',
        ]);

        $line = $this->line($this->build(), $leaver);

        $this->assertSame('2026-07-11', $line->resigned_on?->toDateString());
        $this->assertNull($line->joined_on);
        $this->assertSame('resigned 11 Jul 2026', $line->employmentNote());
    }

    /** Somebody who joined and left inside one period gets both. */
    public function test_both_are_carried_when_both_happened(): void
    {
        $brief = $this->employee('DDD BRIEF', [
            'join_date' => '2026-07-05',
            'employment_status' => 'resigned', 'employment_status_date' => '2026-07-20',
        ]);

        $line = $this->line($this->build(), $brief);

        $this->assertSame('joined 5 Jul 2026, resigned 20 Jul 2026', $line->employmentNote());
    }

    /**
     * A long-standing employee carries neither.
     *
     * This is the assertion that keeps the column meaningful: a join date from
     * last year is not why this month is short, and putting it on every line
     * would make the column say nothing.
     */
    public function test_a_full_month_employee_carries_neither(): void
    {
        $steady = $this->employee('AAA STEADY');

        $line = $this->line($this->build(), $steady);

        $this->assertNull($line->joined_on);
        $this->assertNull($line->resigned_on);
        $this->assertNull($line->employmentNote());
        $this->assertSame(3100.0, (float) $line->basic);
    }

    /**
     * A daily employee who left mid-period gets the note too, even though
     * their basic is not "pro-rated" in the technical sense — their pay is
     * short for exactly the same reason and the question is the same.
     */
    public function test_a_daily_employee_who_left_is_explained_as_well(): void
    {
        $daily = $this->employee('EEE DAILY', [
            'pay_type' => 'daily', 'basic_salary' => 95,
            'employment_status' => 'resigned', 'employment_status_date' => '2026-07-08',
        ]);

        $line = $this->line($this->build(), $daily);

        $this->assertFalse($line->isProrated());
        $this->assertSame('resigned 8 Jul 2026', $line->employmentNote());
    }

    /** It is a SNAPSHOT: rehiring later must not rewrite an old run. */
    public function test_the_reason_survives_the_employee_being_rehired(): void
    {
        $leaver = $this->employee('CCC LEAVER', [
            'employment_status' => 'resigned', 'employment_status_date' => '2026-07-11',
        ]);

        $run = $this->build();
        $this->assertSame('resigned 11 Jul 2026', $this->line($run, $leaver)->employmentNote());

        // Taken back on in November — July must still say what happened in July.
        $leaver->update(['employment_status' => 'confirmed', 'employment_status_date' => '2026-11-01']);

        $this->assertSame(
            'resigned 11 Jul 2026',
            $this->line($run->fresh(['lines']), $leaver)->employmentNote(),
            'A run must keep explaining itself after the employee record moves on.'
        );
    }

    // ── On the documents ──────────────────────────────────────────────────

    private function pdfHtml(PayrollRun $run): string
    {
        $lines = $run->lines()->orderBy('employee_name')->get();

        return view('pdf.payroll-run-list', [
            'company' => $this->company, 'run' => $run, 'lines' => $lines,
            'hasService' => false, 'hasAdjust' => false, 'hasZakat' => false, 'hasSkbbk' => false,
            'hasEmploymentChange' => $lines->contains(fn ($l) => $l->employmentNote() !== null),
            'generatedBy' => $this->user->name,
        ])->render();
    }

    public function test_the_run_pdf_carries_the_column(): void
    {
        $this->employee('BBB JOINER', ['join_date' => '2026-07-17']);

        $html = $this->pdfHtml($this->build());

        $this->assertStringContainsString('Employment', $html);
        $this->assertStringContainsString('joined 17 Jul 2026', $html);
    }

    /** Left out when nobody on the run joined or left inside it. */
    public function test_the_run_pdf_omits_the_column_when_nothing_to_say(): void
    {
        $this->employee('AAA STEADY');

        $html = $this->pdfHtml($this->build());

        $this->assertStringNotContainsString('joined', $html);
        // Still the sheet it was.
        $this->assertStringContainsString('AAA STEADY', $html);
    }

    /** @return array<string, string> heading => value on the first data row. */
    private function excelFirstRow(PayrollRun $run): array
    {
        $response = $this->actingAs($this->user)
            ->get(route('hr.payroll.run-excel', $run))
            ->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $row = [];
        foreach ($sheet->getRowIterator(4, 4)->current()->getCellIterator() as $cell) {
            $heading = trim((string) $cell->getValue());
            if ($heading !== '') {
                $row[$heading] = (string) $sheet->getCell($cell->getColumn() . '5')->getValue();
            }
        }

        return $row;
    }

    public function test_the_excel_carries_the_column(): void
    {
        $this->employee('BBB JOINER', ['join_date' => '2026-07-17']);

        $row = $this->excelFirstRow($this->build());

        $this->assertArrayHasKey('Employment', $row);
        $this->assertSame('joined 17 Jul 2026', $row['Employment']);
        // Beside the Basis that says by how much, not instead of it.
        $this->assertSame('15 of 31 days', $row['Basis']);
    }

    public function test_the_excel_omits_the_column_when_nothing_to_say(): void
    {
        $this->employee('AAA STEADY');

        $row = $this->excelFirstRow($this->build());

        $this->assertArrayNotHasKey('Employment', $row);
        $this->assertArrayHasKey('Basis', $row);
    }

    /** The payslip says it too — the employee is who asks the question. */
    public function test_the_payslip_says_why_the_month_is_short(): void
    {
        $joiner = $this->employee('BBB JOINER', ['join_date' => '2026-07-17']);

        $run = $this->build();

        $html = view('pdf.payslip', [
            'run' => $run, 'lines' => $run->lines()->get(),
            'brandName' => 'Part Co', 'logoBase64' => null,
            'companyName' => $this->company->name, 'companyReg' => null, 'address' => null,
            'employerTaxNumber' => null, 'ratesConfirmed' => true,
        ])->render();

        $this->assertStringContainsString('joined 17 Jul 2026', $html);
        $this->assertStringContainsString('15 of 31 days', $html);
    }
}
