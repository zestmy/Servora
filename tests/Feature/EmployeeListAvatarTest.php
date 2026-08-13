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
 * Every row in the employee list carries a round photo.
 *
 * With a name and a staff id alone, telling two people apart in a list of a
 * hundred means opening records. A face does it at a glance, and the photo is
 * already on file for anyone enrolled for clock-in.
 *
 * Staff without a photo get initials rather than a gap, so the column keeps
 * its shape and rows stay the same height whoever is in them.
 *
 * FOUR PLACES, one component. The main list, the two compliance panels on the
 * same screen, and Compensation. The panels build plain arrays rather than
 * hydrating models, so the component takes either — demanding a model would
 * have meant hydrating one per row to draw a 28px circle.
 */
class EmployeeListAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Face Co', 'slug' => Str::slug('Face Co') . '-' . uniqid(),
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
        Permission::findOrCreate('hr.view', 'web');
        $this->user->givePermissionTo('hr.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(string $name, ?string $photo = null): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => $name, 'is_active' => true, 'join_date' => '2026-01-01',
            'photo_path' => $photo,
        ]);
    }

    private function listHtml(): string
    {
        return $this->actingAs($this->user)->get(route('hr.employees'))->assertOk()->getContent();
    }

    public function test_a_staff_photo_is_shown_for_whoever_has_one(): void
    {
        $withPhoto = $this->employee('NURIN QASRINA', 'employees/photos/nurin.jpg');

        $this->assertStringContainsString(
            route('hr.employees.photo', $withPhoto->id),
            $this->listHtml(),
            'The row should render the photo the employee already has on file.'
        );
    }

    /** A gap would make rows different heights and the column ragged. */
    public function test_staff_without_a_photo_get_initials_instead_of_a_gap(): void
    {
        $this->employee('SITI AMINAH');

        $this->assertStringContainsString('>SI</span>', $this->listHtml(),
            'Two letters off the front of the name, the rule the sidebar already uses.');
    }

    /** Round, per the design system: rounded-full is the avatar shape. */
    public function test_the_avatar_is_round(): void
    {
        $this->employee('SITI AMINAH');

        $this->assertMatchesRegularExpression('/class="[^"]*rounded-full[^"]*"/', $this->listHtml());
    }

    /**
     * The initials sit on a light circle, where gray-400 is 2.54:1 — below AA
     * and below even the 3:1 large-text floor.
     */
    public function test_the_initials_are_legible_on_a_light_surface(): void
    {
        $this->employee('SITI AMINAH');
        $html = $this->listHtml();

        $this->assertStringContainsString('text-gray-600', $html);
        $this->assertStringNotContainsString('text-gray-400 leading-none', $html);
    }

    // ── The other three placements ────────────────────────────────────────

    /**
     * The compliance panel lists people with a lapsing document. Its rows are
     * arrays, so this also pins that photo_path is carried through
     * DocumentExpiry — without it the avatar silently falls back to initials
     * for everyone and nobody notices.
     */
    public function test_the_compliance_panel_shows_photos(): void
    {
        $emp = $this->employee('AZMAN COMPLIANCE', 'employees/photos/azman.jpg');
        $emp->update([
            'food_handler_certified'  => true,
            // Already lapsed, so the row is actionable whatever the warning
            // window is set to.
            'food_handler_expired_on' => now()->subDays(5)->toDateString(),
        ]);

        // A document the company has not said expires is left out of the
        // report entirely, so the fixture has to opt in.
        \App\Models\ComplianceSetting::forCompany($this->company->id)
            ->fill(['food_handler_expires' => true])->save();

        $summary = app(\App\Services\Hr\DocumentExpiry::class)
            ->summarise(Employee::query()->where('id', $emp->id), $this->company->id);

        $rows = collect($summary['rows']);
        $this->assertNotEmpty($rows, 'A lapsed document should produce a compliance row to draw.');

        $this->assertSame('employees/photos/azman.jpg', $rows->first()['photo_path'] ?? null,
            'The compliance row must carry the photo, or every avatar falls back to initials.');
    }

    /** The food-handler chips carry models, so they take the model directly. */
    public function test_the_food_handler_panel_shows_photos(): void
    {
        $missing = $this->employee('CHONG NOCARD', 'employees/photos/chong.jpg');
        $missing->update(['food_handler_certified' => false]);

        $html = $this->listHtml();

        // The chip is a small circle, and h-5 w-5 is used nowhere else on this
        // screen — asserting the photo URL alone would match the main list row
        // and pass whether or not the panel ever rendered one.
        $this->assertStringContainsString('fh-' . $missing->id, $html, 'The chip should be in the panel.');
        $this->assertStringContainsString('h-5 w-5 flex-shrink-0 rounded-full', $html);
    }

    /** And Compensation, whose rows are arrays built by CompensationSummary. */
    public function test_the_compensation_list_shows_photos(): void
    {
        $emp = $this->employee('DEVI PAYROLL', 'employees/photos/devi.jpg');
        $emp->update(['pay_type' => 'monthly', 'basic_salary' => 3000]);

        setPermissionsTeamId($this->company->id);
        foreach (['hr.compensation', 'reports.view'] as $ability) {
            \Spatie\Permission\Models\Permission::findOrCreate($ability, 'web');
        }
        $this->user->givePermissionTo(['hr.compensation', 'reports.view']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $html = \Livewire\Livewire::actingAs($this->user->fresh())
            ->test(\App\Livewire\Hr\Compensation::class)
            ->set('outletFilter', (string) $this->outlet->id)
            ->html();

        $this->assertStringContainsString(route('hr.employees.photo', $emp->id), $html,
            'Compensation rows must carry photo_path through CompensationSummary.');
    }

    /** A one-letter name must not break the initials. */
    public function test_a_very_short_name_still_renders(): void
    {
        $this->employee('A');

        $this->assertStringContainsString('>A</span>', $this->listHtml());
    }
}
