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
 * The one-employee PDF, laid out as a form.
 *
 * It is a personal record leaving the system as a file, so what it must NOT
 * contain matters more than what it must: the pay and employment-standing gates
 * that the edit screen applies have to survive the export, or a button quietly
 * undoes both. Outlet scope likewise — Staff PINs taught that lesson.
 */
class EmployeeDetailsPdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $mine;
    private Outlet $theirs;
    private Employee $employee;
    private Employee $elsewhere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'PDF Co', 'slug' => Str::slug('PDF Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->mine   = $this->outlet('Mine', 'MINE');
        $this->theirs = $this->outlet('Theirs', 'THRS');

        $this->employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->mine->id,
            'name' => 'AISYAH BINTI RAHMAN', 'designation' => 'Sous Chef',
            'basic_salary' => 4200, 'bank_name' => 'MAYBANK', 'bank_account_no' => '1234567890',
            'employment_status' => 'confirmed', 'is_active' => true,
        ]);

        $this->elsewhere = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->theirs->id,
            'name' => 'BALQIS', 'is_active' => true,
        ]);

        foreach (['hr.view', 'hr.compensation', 'hr.employment'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    /** @param array<int, string> $abilities */
    private function manager(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->mine->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function pdfText(User $user, Employee $employee): string
    {
        $response = $this->actingAs($user)->get(route('hr.employees.details-pdf', $employee));
        $response->assertOk();

        // dompdf's stream() returns an ordinary response with the file as its
        // body, not a StreamedResponse — the method name is its own, not Laravel's.
        return $response->getContent();
    }

    public function test_it_renders_a_form_for_an_employee_you_administer(): void
    {
        $pdf = $this->pdfText($this->manager(['hr.view']), $this->employee);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf), 'A one-page form should not be near-empty.');
    }

    public function test_pay_is_absent_without_the_pay_ability(): void
    {
        $without = $this->pdfText($this->manager(['hr.view']), $this->employee);
        $with    = $this->pdfText($this->manager(['hr.view', 'hr.compensation']), $this->employee);

        // dompdf compresses its streams, so the reliable signal is that the
        // permitted file carries a section the other does not.
        $this->assertGreaterThan(
            strlen($without),
            strlen($with),
            'The pay section must only reach the file for somebody allowed to see it.'
        );
    }

    public function test_an_employee_at_another_branch_is_refused(): void
    {
        $this->actingAs($this->manager(['hr.view']))
            ->get(route('hr.employees.details-pdf', $this->elsewhere))
            ->assertForbidden();
    }

    public function test_it_needs_the_hr_view_ability_at_all(): void
    {
        $this->actingAs($this->manager([]))
            ->get(route('hr.employees.details-pdf', $this->employee))
            ->assertForbidden();
    }
}
