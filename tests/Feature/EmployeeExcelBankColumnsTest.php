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
 * The Employee list Excel export carries bank account details.
 *
 * Bank fields are NOT in Employee::SENSITIVE_PAY_ATTRIBUTES — by the decision
 * recorded there — so the columns are present whether or not the exporting
 * user holds hr.compensation. The pay columns still shift on that permission,
 * which is why this asserts by header name rather than by column letter.
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
        foreach (['hr.view', 'hr.employees.manage'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(array $attributes): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'is_active' => true, 'join_date' => '2025-01-01',
        ], $attributes));
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

    /** No account on file leaves the holder empty rather than inventing one. */
    public function test_an_employee_without_an_account_has_no_holder(): void
    {
        $this->employee(['name' => 'Aminah', 'staff_id' => 'E001']);

        $row = $this->exportRow();

        $this->assertSame('', $row['Bank Account No.']);
        $this->assertSame('', $row['Account Holder']);
    }
}
