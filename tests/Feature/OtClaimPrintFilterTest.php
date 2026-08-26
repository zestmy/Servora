<?php

namespace Tests\Feature;

use App\Livewire\Hr\OvertimeClaims;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\OvertimeClaim;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Print Approved OT Claims", narrowed by section and employment.
 *
 * The all-employees print is a stack of signable forms, one per person, and the
 * two new filters decide who gets a page. They have to mean here exactly what
 * they mean on the list — including the synthetic options, where 'none' is a
 * real choice ("no status recorded") and not the absence of a filter, and
 * "exclude outsourcing" keeps people who have no status at all.
 */
class OtClaimPrintFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $kitchen;
    private Section $service;
    private User $submitter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'OT Co', 'slug' => Str::slug('OT Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->kitchen = Section::create(['company_id' => $this->company->id, 'name' => 'Kitchen', 'is_active' => true]);
        $this->service = Section::create(['company_id' => $this->company->id, 'name' => 'Service', 'is_active' => true]);

        Permission::findOrCreate('hr.claims', 'web');

        // Every claim records who raised it; the column is not nullable.
        $this->submitter = User::factory()->create(['company_id' => $this->company->id]);
    }

    private function employee(string $name, Section $section, ?string $employmentStatus): Employee
    {
        $employee = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'section_id' => $section->id,
            'employment_status' => $employmentStatus, 'is_active' => true,
        ]);

        OvertimeClaim::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'employee_id' => $employee->id, 'submitted_by' => $this->submitter->id,
            'claim_date' => '2026-08-10',
            'ot_time_start' => '18:00', 'ot_time_end' => '20:00', 'total_ot_hours' => 2,
            'ot_type' => 'normal_day', 'reason' => 'Stocktake', 'status' => 'approved',
        ]);

        return $employee;
    }

    private function manager(): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo('hr.claims');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * Who got a page. Read off the view data rather than the file: dompdf
     * compresses its streams, so the rendered bytes cannot be searched for a
     * name.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function printedNames(User $user, array $filters): array
    {
        $printed = [];

        Event::listen('composing: pdf.ot-claims-all', function ($view) use (&$printed) {
            $printed = collect($view->getData()['grouped'])
                ->map(fn ($group) => $group['employee']->name)
                ->all();
        });

        $this->actingAs($user)
            ->get(route('hr.ot-claims.pdf', ['employee' => 'all'] + $filters))
            ->assertOk();

        return $printed;
    }

    public function test_without_the_new_filters_everyone_with_approved_claims_is_printed(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('BALQIS', $this->service, 'probation');

        $printed = $this->printedNames($this->manager(), ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertEqualsCanonicalizing(['AISYAH', 'BALQIS'], $printed);
    }

    public function test_section_narrows_the_stack_to_that_section(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('BALQIS', $this->service, 'confirmed');

        $printed = $this->printedNames($this->manager(), [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'section' => $this->kitchen->id,
        ]);

        $this->assertSame(['AISYAH'], $printed);
    }

    public function test_employment_status_narrows_the_stack(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('BALQIS', $this->kitchen, 'probation');

        $printed = $this->printedNames($this->manager(), [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'employment' => 'probation',
        ]);

        $this->assertSame(['BALQIS'], $printed);
    }

    public function test_exclude_outsourcing_drops_outsourced_staff_but_keeps_the_unrecorded(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('BALQIS', $this->kitchen, 'outsourcing');
        $this->employee('CHONG', $this->kitchen, null);

        $printed = $this->printedNames($this->manager(), [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'employment' => 'exclude_outsourcing',
        ]);

        $this->assertEqualsCanonicalizing(['AISYAH', 'CHONG'], $printed);
    }

    public function test_no_status_is_a_filter_of_its_own_not_the_absence_of_one(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('CHONG', $this->kitchen, null);

        $printed = $this->printedNames($this->manager(), [
            'from' => '2026-08-01', 'to' => '2026-08-31', 'employment' => 'none',
        ]);

        $this->assertSame(['CHONG'], $printed);
    }

    public function test_section_and_employment_narrow_together(): void
    {
        $this->employee('AISYAH', $this->kitchen, 'confirmed');
        $this->employee('BALQIS', $this->kitchen, 'probation');
        $this->employee('CHONG', $this->service, 'confirmed');

        $printed = $this->printedNames($this->manager(), [
            'from' => '2026-08-01', 'to' => '2026-08-31',
            'section' => $this->kitchen->id, 'employment' => 'confirmed',
        ]);

        $this->assertSame(['AISYAH'], $printed);
    }

    public function test_naming_one_employee_still_prints_them(): void
    {
        // Section and employment narrow WHO gets a page; naming a person is the
        // narrower answer, so the single-employee document is not re-filtered.
        $employee = $this->employee('AISYAH', $this->kitchen, 'confirmed');

        $this->actingAs($this->manager())
            ->get(route('hr.ot-claims.pdf', [
                'employee' => $employee->id,
                'from' => '2026-08-01', 'to' => '2026-08-31',
                'section' => $this->service->id,
            ]))
            ->assertOk();
    }

    public function test_the_print_modal_starts_from_the_lists_own_narrowing(): void
    {
        /*
         * The component is driven directly rather than through Livewire::test():
         * rendering this screen runs its weekly-trend aggregate, which is raw
         * MySQL (WEEKDAY / DATE_SUB) and cannot execute on the SQLite the suite
         * uses. openPdfModal() touches neither the database nor the view, so
         * this still exercises the rule it is here for.
         */
        $component = new OvertimeClaims();
        $component->sectionFilter          = (string) $this->kitchen->id;
        $component->employmentStatusFilter = 'confirmed';
        $component->dateFrom               = '2026-08-01';
        $component->dateTo                 = '2026-08-31';

        $component->openPdfModal();

        $this->assertSame((string) $this->kitchen->id, $component->pdfSectionId);
        $this->assertSame('confirmed', $component->pdfEmploymentStatus);
        $this->assertSame('2026-08-01', $component->pdfFrom);
        $this->assertSame('2026-08-31', $component->pdfTo);
    }

    public function test_the_print_url_carries_every_filter_it_was_given(): void
    {
        $component = new OvertimeClaims();
        $component->pdfFrom            = '2026-08-01';
        $component->pdfTo              = '2026-08-31';
        $component->pdfSectionId       = (string) $this->kitchen->id;
        $component->pdfEmploymentStatus = 'exclude_outsourcing';
        $component->outletFilter       = (string) $this->outlet->id;

        $url = $component->getPdfUrl();

        $this->assertStringContainsString('section=' . $this->kitchen->id, $url);
        $this->assertStringContainsString('employment=exclude_outsourcing', $url);
        $this->assertStringContainsString('outlet=' . $this->outlet->id, $url);
        $this->assertStringContainsString('/pdf/all', $url);
    }
}
