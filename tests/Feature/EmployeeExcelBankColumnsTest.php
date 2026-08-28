<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Employee list Excel export carries bank account details, for the people
 * allowed to see them.
 *
 * Gated on hr.compensation alongside salary and service points. The gate is
 * applied in the CONTROLLER rather than by adding the columns to
 * Employee::SENSITIVE_PAY_ATTRIBUTES, which would also hide them on the
 * Personal tab of the employee form and reverse the 2026-08-11 decision that
 * put them there — so the two are asserted apart: the columns leave the file,
 * and the model's list is untouched.
 *
 * Everything is asserted by HEADER NAME rather than by column letter, because
 * the gate shifts the layout by three columns.
 *
 * The account number is asserted as a STRING with its leading zero intact:
 * written as a number, "0123456789" comes back 123456789 and every account it
 * touches is wrong in a way nobody notices until a transfer bounces.
 */
class EmployeeExcelBankColumnsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Bank Co', 'slug' => Str::slug('Bank Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.employees.manage', 'hr.compensation'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(array $attributes): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'is_active' => true, 'join_date' => '2025-01-01',
        ], $attributes));
    }

    /** Strip the pay permission from the fixture user. */
    private function withoutCompensation(): void
    {
        $this->user->revokePermissionTo('hr.compensation');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<string, string> header => value, for the given row. */
    private function exportRow(int $dataRow = 5): array
    {
        // A download response, so the workbook is read from the file it points
        // at rather than from a rendered body.
        $response = $this->actingAs($this->user)
            ->get(route('hr.employees.export-excel'))
            ->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet();

        $row = [];
        foreach ($sheet->getRowIterator(4, 4)->current()->getCellIterator() as $cell) {
            $header = (string) $cell->getValue();
            if ($header === '') {
                continue;
            }
            $row[$header] = (string) $sheet->getCell($cell->getColumn() . $dataRow)->getValue();
        }

        return $row;
    }

    public function test_the_export_has_the_bank_columns(): void
    {
        $this->employee([
            'name' => 'Aminah', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $row = $this->exportRow();

        $this->assertArrayHasKey('Bank', $row);
        $this->assertArrayHasKey('Bank Account No.', $row);
        $this->assertArrayHasKey('Account Holder', $row);

        $this->assertSame('MAYBANK', $row['Bank']);
        $this->assertSame('0123456789', $row['Bank Account No.'],
            'A leading zero dropped here silently corrupts the account number.');
    }

    /** Blank holder means the account is their own — spell that out. */
    public function test_the_holder_falls_back_to_the_employee(): void
    {
        $this->employee([
            'name' => 'Aminah', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $this->assertSame('Aminah', $this->exportRow()['Account Holder']);
    }

    /** A third-party account prints the person actually holding it. */
    public function test_a_third_party_holder_is_printed(): void
    {
        $this->employee([
            'name' => 'Aminah', 'staff_id' => 'E001',
            'bank_name' => 'CIMB', 'bank_account_no' => '55501',
            'bank_account_name' => 'SITI BINTI ALI',
        ]);

        $this->assertSame('SITI BINTI ALI', $this->exportRow()['Account Holder']);
    }

    /**
     * Without hr.compensation the columns are not in the file at all.
     *
     * Asserted on the HEADER ROW, not on the account number: a file that
     * merely leaves the values blank still tells the reader the company holds
     * bank details and that this person's are missing, which is not what
     * withholding them means.
     */
    public function test_the_bank_columns_are_absent_without_the_pay_ability(): void
    {
        $this->employee([
            'name' => 'Aminah', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $this->withoutCompensation();

        $row = $this->exportRow();

        $this->assertArrayNotHasKey('Bank', $row);
        $this->assertArrayNotHasKey('Bank Account No.', $row);
        $this->assertArrayNotHasKey('Account Holder', $row);

        // The columns that are NOT gated must still be there — a gate that
        // took the rest of the sheet with it would also pass the three
        // assertions above.
        $this->assertArrayHasKey('Name', $row);
        $this->assertArrayHasKey('Join Date', $row);
        $this->assertArrayHasKey('Status', $row);
        $this->assertSame('Aminah', $row['Name']);
    }

    /**
     * The values do not survive the gate either, anywhere in the sheet.
     *
     * The headers moving is the visible half; this is the half that matters,
     * because a value written under a shifted header is a leak that reads as
     * a layout bug.
     */
    public function test_the_account_number_is_nowhere_in_the_file_without_the_ability(): void
    {
        $this->employee([
            'name' => 'Aminah', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $this->withoutCompensation();

        $this->assertNotContains('0123456789', array_values($this->exportRow()));
        $this->assertNotContains('MAYBANK', array_values($this->exportRow()));
    }

    /**
     * The model's list is deliberately NOT the gate.
     *
     * Adding the three columns there would have been the one-line fix and
     * would also have hidden them on the employee form, undoing a decision
     * made on purpose. Pinned so that shortcut is not taken later.
     */
    public function test_bank_fields_stay_off_the_sensitive_pay_list(): void
    {
        foreach (['bank_name', 'bank_account_no', 'bank_account_name'] as $column) {
            $this->assertNotContains(
                $column,
                Employee::SENSITIVE_PAY_ATTRIBUTES,
                'Gating belongs in the export, not on the model — see the note on the constant.'
            );
        }
    }

    /** No account on file leaves the holder empty rather than inventing one. */
    public function test_an_employee_without_an_account_has_no_holder(): void
    {
        $this->employee(['name' => 'Aminah', 'staff_id' => 'E001']);

        $row = $this->exportRow();

        $this->assertSame('', $row['Bank Account No.']);
        $this->assertSame('', $row['Account Holder']);
    }
}
