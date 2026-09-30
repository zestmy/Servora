<?php

namespace Tests\Feature;

use App\Livewire\Hr\DutyRoster;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Roster;
use App\Models\RosterEntry;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The duty roster page is open to every signed-in user on purpose — staff read
 * their shifts there. So the page's route cannot be what protects its write
 * actions: each action checks its own ability, and every entry id is resolved
 * inside the roster on screen (RosterEntry has no CompanyScope).
 */
class RosterDutyPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $foh;
    private Employee $employee;
    private Roster $roster;
    private RosterEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->company, $this->outlet, $this->foh] = $this->tenant('Roster Perm Co');

        $this->employee = Employee::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'section_id' => $this->foh->id, 'name' => 'Aisyah', 'is_active' => true,
        ]);

        [$this->roster, $this->entry] = $this->draftRoster($this->company, $this->outlet, $this->foh, $this->employee);

        foreach (['roster.view', 'roster.create', 'roster.edit', 'roster.approve', 'roster.amend'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /** @return array{0: Company, 1: Outlet, 2: Section} */
    private function tenant(string $name): array
    {
        $company = Company::create([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main ' . $name, 'code' => strtoupper(Str::random(4)), 'is_active' => true,
        ]);
        $section = Section::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'name' => 'FOH', 'sort_order' => 1, 'is_active' => true,
        ]);

        return [$company, $outlet, $section];
    }

    /** @return array{0: Roster, 1: RosterEntry} */
    private function draftRoster(Company $company, Outlet $outlet, Section $section, Employee $employee): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY);

        $roster = Roster::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id, 'section_id' => $section->id,
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'status' => Roster::STATUS_DRAFT, 'revision' => 1,
        ]);

        // The page looks the week up by a bare 'Y-m-d' string, as MySQL's DATE
        // column stores it; SQLite would otherwise keep the cast's time part.
        \Illuminate\Support\Facades\DB::table('rosters')->where('id', $roster->id)->update([
            'week_start_date' => $start->toDateString(),
            'week_end_date'   => $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        ]);

        $entry = RosterEntry::create([
            'roster_id' => $roster->id, 'employee_id' => $employee->id,
            'day_date' => $start->toDateString(), 'is_off_day' => true, 'leave_type' => 'off',
            'rest_duration' => 60, 'sort_order' => 0,
        ]);

        return [$roster, $entry];
    }

    private function user(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        if ($abilities) {
            $user->givePermissionTo($abilities);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_a_reader_cannot_delete_an_entry(): void
    {
        Livewire::actingAs($this->user(['roster.view']))
            ->test(DutyRoster::class)
            ->call('deleteEntry', $this->entry->id)
            ->assertForbidden();

        $this->assertNotNull(RosterEntry::find($this->entry->id));
    }

    public function test_a_reader_cannot_save_an_entry(): void
    {
        Livewire::actingAs($this->user(['roster.view']))
            ->test(DutyRoster::class)
            ->set('f_employee_id', $this->employee->id)
            ->set('f_day_date', now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString())
            ->set('f_is_off_day', true)
            ->call('saveEntry')
            ->assertForbidden();

        $this->assertSame(1, RosterEntry::where('roster_id', $this->roster->id)->count());
    }

    public function test_a_reader_cannot_email_the_roster(): void
    {
        Livewire::actingAs($this->user(['roster.view']))
            ->test(DutyRoster::class)
            ->set('email_to_employees', false)
            ->set('email_additional', 'someone@example.test')
            ->call('sendEmail')
            ->assertForbidden();
    }

    public function test_an_editor_can_delete_and_save_entries(): void
    {
        $editor = $this->user(['roster.edit']);

        Livewire::actingAs($editor)
            ->test(DutyRoster::class)
            ->set('f_employee_id', $this->employee->id)
            ->set('f_day_date', now()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString())
            ->set('f_is_off_day', true)
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->call('deleteEntry', $this->entry->id)
            ->assertOk();

        $this->assertNull(RosterEntry::find($this->entry->id));
        $this->assertSame(1, RosterEntry::where('roster_id', $this->roster->id)->count());
    }

    public function test_an_editor_cannot_delete_another_companys_entry(): void
    {
        [$otherCompany, $otherOutlet, $otherSection] = $this->tenant('Other Roster Co');
        $stranger = Employee::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id, 'outlet_id' => $otherOutlet->id,
            'section_id' => $otherSection->id, 'name' => 'Stranger', 'is_active' => true,
        ]);
        [, $theirEntry] = $this->draftRoster($otherCompany, $otherOutlet, $otherSection, $stranger);

        $component = Livewire::actingAs($this->user(['roster.edit']))->test(DutyRoster::class);

        // Resolved inside the roster on screen, so another company's id is
        // simply not found (the handler renders that as a 404).
        try {
            $component->call('deleteEntry', $theirEntry->id);
            $this->fail('Another company\'s roster entry was reachable.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // expected
        }

        $this->assertNotNull(RosterEntry::find($theirEntry->id));
    }
}
