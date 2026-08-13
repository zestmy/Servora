<?php

namespace Tests\Feature;

use App\Livewire\Hr\EmployeeForm;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Hr\DocumentExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A passport and a visa are kept on the record, and warn before they lapse.
 *
 * A foreign worker's right to be at work runs out on a date and the company
 * carries the consequence of missing it. The record held an IC number and a
 * nationality, so those dates lived in a spreadsheet or nowhere.
 *
 * The reminders are not a new mechanism: DocumentExpiry already walks
 * Employee::COMPLIANCE_DOCUMENTS for the certifications, and these two join
 * that list — so they appear in the same compliance panel, under the same
 * warning window, with no second thing to maintain.
 *
 * THE ONE REAL DIFFERENCE is that they are OPTIONAL. The certifications are
 * asked of everybody, so a blank is worth reporting; most staff are local and
 * hold neither of these, and reporting the whole payroll as missing a passport
 * would bury the handful of dates that matter.
 */
class PassportVisaExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Permit Co', 'slug' => Str::slug('Permit Co') . '-' . uniqid(),
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
        foreach (['hr.view', 'hr.employees.manage', 'hr.employment'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.view', 'hr.employees.manage', 'hr.employment']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(string $name, array $extra = []): Employee
    {
        return Employee::create(array_merge([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2026-01-01',
        ], $extra));
    }

    /**
     * Only the two documents under test. The built-in certifications report a
     * blank as missing by design — typhoid does it for every fixture here —
     * and asserting on the whole list would make this test about them.
     *
     * @return array<int, string>
     */
    private function permitKeys(): array
    {
        return $this->findings()
            ->pluck('document_key')
            ->filter(fn ($k) => in_array($k, ['passport', 'visa'], true))
            ->sort()->values()->all();
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function findings(): \Illuminate\Support\Collection
    {
        return collect(app(DocumentExpiry::class)
            ->summarise(Employee::query()->where('company_id', $this->company->id), $this->company->id)['rows']);
    }

    // ── The record holds them ─────────────────────────────────────────────

    public function test_a_passport_and_visa_save_from_the_form(): void
    {
        $emp = $this->employee('RAHUL FOREIGN');

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $emp->id])
            ->set('f_passport_number', 'A12345678')
            ->set('f_passport_expired_on', '2027-06-30')
            ->set('f_visa_number', 'VP-99881')
            ->set('f_visa_expired_on', '2027-01-15')
            ->call('save')
            ->assertHasNoErrors();

        $emp->refresh();

        $this->assertSame('A12345678', $emp->passport_number);
        $this->assertSame('2027-06-30', $emp->passport_expired_on->toDateString());
        $this->assertSame('VP-99881', $emp->visa_number);
        $this->assertSame('2027-01-15', $emp->visa_expired_on->toDateString());
    }

    /** Reopening shows them, or they are write-only and nobody notices. */
    public function test_they_come_back_when_the_record_is_reopened(): void
    {
        $emp = $this->employee('RAHUL FOREIGN', [
            'passport_number' => 'A12345678', 'passport_expired_on' => '2027-06-30',
            'visa_number' => 'VP-99881', 'visa_expired_on' => '2027-01-15',
        ]);

        Livewire::actingAs($this->user)->test(EmployeeForm::class, ['id' => $emp->id])
            ->assertSet('f_passport_number', 'A12345678')
            ->assertSet('f_passport_expired_on', '2027-06-30')
            ->assertSet('f_visa_number', 'VP-99881')
            ->assertSet('f_visa_expired_on', '2027-01-15');
    }

    // ── The reminders ─────────────────────────────────────────────────────

    public function test_a_visa_running_out_is_reported(): void
    {
        $this->employee('RAHUL FOREIGN', [
            'visa_number' => 'VP-99881', 'visa_expired_on' => now()->addDays(7)->toDateString(),
        ]);

        $visa = $this->findings()->firstWhere('document_key', 'visa');

        $this->assertNotNull($visa, 'A permit expiring in a week is exactly what needs saying.');
        $this->assertSame('RAHUL FOREIGN', $visa['name']);
    }

    public function test_a_passport_already_lapsed_is_reported(): void
    {
        $this->employee('RAHUL FOREIGN', [
            'passport_number' => 'A12345678', 'passport_expired_on' => now()->subDays(3)->toDateString(),
        ]);

        $passport = $this->findings()->firstWhere('document_key', 'passport');

        $this->assertNotNull($passport);
        $this->assertSame(DocumentExpiry::EXPIRED, $passport['state']);
    }

    /** Both at once, since a permit and the passport behind it run separately. */
    public function test_both_are_reported_independently(): void
    {
        $this->employee('RAHUL FOREIGN', [
            'passport_number' => 'A12345678', 'passport_expired_on' => now()->addDays(5)->toDateString(),
            'visa_number' => 'VP-99881', 'visa_expired_on' => now()->addDays(9)->toDateString(),
        ]);

        $this->assertSame(['passport', 'visa'], $this->permitKeys());
    }

    // ── What must NOT be reported ─────────────────────────────────────────

    /**
     * The point of the optional flag. Without it every local on the payroll
     * would be reported as missing a passport, burying the dates that matter.
     */
    public function test_local_staff_are_not_reported_as_missing_a_passport(): void
    {
        $this->employee('SITI LOCAL');

        $this->assertSame([], $this->permitKeys(),
            'Somebody who was never asked for a passport is not a finding.');
    }

    /** A passport in date is not news either. */
    public function test_a_document_with_years_left_is_not_reported(): void
    {
        $this->employee('RAHUL FOREIGN', [
            'passport_number' => 'A12345678', 'passport_expired_on' => now()->addYears(4)->toDateString(),
        ]);

        $this->assertSame([], $this->permitKeys());
    }

    /**
     * A number with no date cannot expire, so it is reported as undated rather
     * than silently ignored — somebody has to go and find the date.
     */
    public function test_a_passport_with_no_expiry_is_flagged_as_undated(): void
    {
        $this->employee('RAHUL FOREIGN', ['passport_number' => 'A12345678']);

        $passport = $this->findings()->firstWhere('document_key', 'passport');

        $this->assertNotNull($passport);
        $this->assertSame(DocumentExpiry::UNDATED, $passport['state']);
    }

    /** The certifications still report a blank as missing, as they always did. */
    public function test_the_certifications_are_unchanged_by_this(): void
    {
        \App\Models\ComplianceSetting::forCompany($this->company->id)
            ->fill(['food_handler_expires' => true])->save();

        $this->employee('SITI LOCAL', ['food_handler_certified' => false]);

        $this->assertNotNull($this->findings()->firstWhere('document_key', 'food_handler'),
            'A document asked of everybody is still a finding when it is blank.');
    }
}
