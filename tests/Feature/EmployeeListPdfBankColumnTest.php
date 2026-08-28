<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Employee list PDF carries bank account details.
 *
 * ONE column, not the sheet's three. A4 landscape at 12mm margins is ~1032px
 * and the fixed columns already spend 840 of it when pay is shown, so three
 * more would take more width than the Name column has left. The account number
 * leads, with bank and — only when it is somebody else's account — the holder
 * on the `.sub` line beneath, which is the same idiom the cert-no and typhoid
 * columns already use.
 *
 * The assertions are made against the rendered VIEW rather than the PDF bytes,
 * because dompdf compresses its streams and `assertStringContainsString` on the
 * file is worth nothing. One test still goes through dompdf end to end, to
 * catch markup the renderer chokes on rather than merely renders wrongly.
 */
class EmployeeListPdfBankColumnTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Bank PDF Co', 'slug' => Str::slug('Bank PDF Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);
    }

    private function employee(array $attributes): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'is_active' => true, 'join_date' => '2025-01-01',
        ], $attributes));
    }

    private function html(bool $canViewPay = true): string
    {
        return view('pdf.employees', [
            'employees'  => Employee::with(['outlet', 'section'])->orderBy('name')->get(),
            'filters'    => [],
            'brandName'  => 'Bank PDF Co',
            'logoBase64' => null,
            'canViewPay' => $canViewPay,
        ])->render();
    }

    public function test_the_column_carries_the_account_and_the_bank(): void
    {
        $this->employee([
            'name' => 'AMINAH', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $html = $this->html();

        $this->assertStringContainsString('Bank Account', $html);
        $this->assertStringContainsString('0123456789', $html);
        $this->assertStringContainsString('MAYBANK', $html);
    }

    /**
     * Their own account does not reprint their own name — it is already the
     * second column of the same row, and the width is needed elsewhere.
     */
    public function test_an_own_account_does_not_repeat_the_name(): void
    {
        $this->employee([
            'name' => 'AMINAH', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
            'bank_account_name' => 'AMINAH',
        ]);

        $this->assertStringNotContainsString('a/c:', $this->html());
    }

    /** A third-party account names the holder, which is the point of showing it. */
    public function test_a_third_party_account_names_the_holder(): void
    {
        $this->employee([
            'name' => 'AMINAH', 'staff_id' => 'E001',
            'bank_name' => 'CIMB', 'bank_account_no' => '55501',
            'bank_account_name' => 'SITI BINTI ALI',
        ]);

        $this->assertStringContainsString('a/c: SITI BINTI ALI', $this->html());
    }

    /**
     * Every row has as many cells as the header has columns.
     *
     * Inserting a column is exactly how a row gets shifted one to the left for
     * the whole rest of its width, which reads as "the data is wrong" rather
     * than "a cell is missing" — and it happened once while writing this.
     * Checked on both sides of the pay gate, since that gate adds columns to
     * the header and the body in two separate places.
     */
    public function test_every_row_has_a_cell_for_every_column(): void
    {
        $this->employee([
            'name' => 'AMINAH', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);
        $this->employee(['name' => 'BALQIS', 'staff_id' => 'E002']);

        foreach ([true, false] as $canViewPay) {
            $html = $this->html($canViewPay);

            $columns = substr_count(
                (string) preg_replace('/.*<thead>(.*?)<\/thead>.*/s', '$1', $html),
                '<th'
            );

            preg_match_all('/<tr>(?:(?!<\/tr>).)*?<td[^>]*>\s*\d+\s*<\/td>.*?<\/tr>/s', $html, $rows);
            $this->assertNotEmpty($rows[0], 'The fixture rows must be found for this to assert anything.');

            foreach ($rows[0] as $row) {
                $this->assertSame(
                    $columns,
                    substr_count($row, '<td'),
                    'A row is missing a cell, so everything after it sits under the wrong heading.'
                );
            }
        }
    }

    /** The outlet subheading and the empty state span the whole table. */
    public function test_the_spanning_rows_cover_every_column(): void
    {
        $html = $this->html();
        $this->assertStringContainsString('colspan="16"', $html,
            'The outlet row must span the pay layout in full.');

        $this->assertStringContainsString('colspan="14"', $this->html(false));
    }

    /** dompdf renders it, at the new width, without choking. */
    public function test_the_route_returns_a_real_pdf(): void
    {
        $this->employee([
            'name' => 'AMINAH', 'staff_id' => 'E001',
            'bank_name' => 'MAYBANK', 'bank_account_no' => '0123456789',
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.view', 'hr.compensation'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $user->givePermissionTo(['hr.view', 'hr.compensation']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $pdf = $this->actingAs($user)
            ->get(route('hr.employees.export-pdf'))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }
}
