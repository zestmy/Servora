<?php

namespace Tests\Feature;

use App\Livewire\Hr\RosterPdfImportModal;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Roster;
use App\Models\RosterDayRemark;
use App\Models\RosterEntry;
use App\Models\RosterNameAlias;
use App\Models\RosterStation;
use App\Models\Section;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\RosterPdfImport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Upload an Excel-style roster PDF, review it, import it.
 *
 * The PDF is made here with dompdf rather than checked in: a real outlet's
 * sheet carries real staff names, and a second PDF producer also proves the
 * reader keys on the layout, not on one file's quirks.
 */
class RosterPdfImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Section $foh;
    private Employee $alpha;
    private Employee $bravo;
    private Employee $charlie;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-26 10:00:00'));

        $this->company = Company::create([
            'name' => 'Roster Co', 'slug' => Str::slug('Roster Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);
        $this->foh = Section::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'name' => 'FOH', 'sort_order' => 1, 'is_active' => true,
        ]);

        $make = fn (string $name) => Employee::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'section_id' => $this->foh->id, 'name' => $name, 'is_active' => true,
        ]);
        $this->alpha = $make('Muhammad Alpha bin Ahmad');
        $this->bravo = $make('Siti Bravo-One');
        $this->charlie = $make('Charlie Tan');

        foreach (['roster.create', 'roster.edit'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(array $abilities = ['roster.create', 'roster.edit']): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** An FOH sheet the way an outlet builds it in Excel. */
    private function pdf(array $alphaWeek = ['7AM-3.30PM', 'OFF DAY', '12PM-8.30PM', 'AL', '9AM-9PM', 'MITEC EVENT', '2.00PM-10.30PM']): UploadedFile
    {
        $td = fn ($t) => "<td style='width:95px;text-align:center'>{$t}</td>";
        $row = fn (string $label, array $cells) => "<tr><td style='width:120px'>{$label}</td>" . implode('', array_map($td, $cells)) . '<td></td></tr>';

        $html = "<html><body style='font-family:DejaVu Sans;font-size:9px'>"
            . '<p>DUTY ROSTER — KLCC</p><table>'
            . $row('DATE', ['28-Sep', '29-Sep', '30-Sep', '1-Oct', '2-Oct', '3-Oct', '4-Oct'])
            . $row('WEEK 40', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'])
            . $row('EVENTS', ['', '', '', '', 'BIG EVENT', '', ''])
            . $row('1 ALPHA', $alphaWeek)
            . $row('MANAGER', ['MOD AM', '', '', '', 'BAR PM', '', 'MOD PM'])
            . $row('2 BRAVO-ONE', ['OFF DAY', '10.30AM-10.30PM', 'MC', 'CLAIM HOUR', '8AM-8PM', '8AM-8PM', 'OFF'])
            . $row('WAITER', ['', '', '', '', '', '', ''])
            . $row('3 ZULU', ['9AM-5PM', '9AM-5PM', '9AM-5PM', '9AM-5PM', '9AM-5PM', 'OFF', 'OFF'])
            . $row('RELIEF', ['', '', '', '', '', '', ''])
            . $row('OPENING', ['3', '3', '3', '3', '3', '3', '3'])
            . '</table><p>NOTICE: roster changes need approval</p></body></html>';

        $bytes = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();

        return UploadedFile::fake()->createWithContent('KLCC FOH SCHEDULE.pdf', $bytes);
    }

    private function modal(User $user)
    {
        return Livewire::actingAs($user)
            ->test(RosterPdfImportModal::class)
            ->call('show', $this->outlet->id, null);
    }

    public function test_uploading_reads_the_week_and_proposes_who_is_who(): void
    {
        $modal = $this->modal($this->user())->set('pdf', $this->pdf());

        $modal->assertSet('error', '')
            ->assertSet('parsed.week_start', '2026-09-28')
            // The file is called "… FOH SCHEDULE", so the section is picked for them.
            ->assertSet('sectionId', $this->foh->id)
            ->assertSet('assign.0', (string) $this->alpha->id)
            ->assertSet('how.0', 'name')
            ->assertSet('assign.1', (string) $this->bravo->id)
            // Nobody called Zulu works here: left for a person to decide.
            ->assertSet('assign.2', '')
            ->assertSet('how.2', 'none')
            ->assertSee('No match — pick the employee')
            ->assertSee('MOD AM');

        $this->assertSame(0, Roster::withoutGlobalScopes()->count(), 'nothing is written before Import');
    }

    public function test_import_writes_the_week_and_remembers_the_names(): void
    {
        $am = Shift::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'name' => 'AM',
            'start_time' => '07:00', 'end_time' => '15:30', 'rest_duration' => 30, 'normal_hours' => 8, 'is_active' => true,
        ]);
        RosterStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Mod AM', 'is_active' => true]);

        $this->modal($this->user())
            ->set('pdf', $this->pdf())
            ->set('assign.2', (string) $this->charlie->id)
            // Only one of the two unknown stations is wanted as a station.
            ->set('createStations', ['BAR PM'])
            ->call('import')
            ->assertSet('open', false)
            ->assertDispatched('roster-pdf-imported', weekStart: '2026-09-28', sectionId: $this->foh->id);

        $roster = Roster::withoutGlobalScopes()->sole();
        $this->assertTrue($roster->isDraft());
        $this->assertSame($this->foh->id, $roster->section_id);

        $entry = fn (Employee $e, string $date) => RosterEntry::where('roster_id', $roster->id)
            ->where('employee_id', $e->id)->whereDate('day_date', $date)->first();

        // Monday: times, the existing station matched case-insensitively,
        // and the shift template with its own break.
        $mon = $entry($this->alpha, '2026-09-28');
        $this->assertSame('07:00:00', $mon->shift_start);
        $this->assertSame('Mod AM', $mon->station->name);
        $this->assertSame($am->id, $mon->shift_id);
        $this->assertSame(30, $mon->rest_duration);

        $this->assertTrue($entry($this->alpha, '2026-09-29')->is_off_day);
        $this->assertSame('al', $entry($this->alpha, '2026-10-01')->leave_type);
        $this->assertSame('BAR PM', $entry($this->alpha, '2026-10-02')->station->name);

        // No times on the sheet: a working day, with what the sheet said.
        $sat = $entry($this->alpha, '2026-10-03');
        $this->assertFalse($sat->is_off_day);
        $this->assertNull($sat->shift_start);
        $this->assertSame('MITEC EVENT', $sat->notes);

        // A station they chose not to create stays as a note.
        $sun = $entry($this->alpha, '2026-10-04');
        $this->assertNull($sun->station_id);
        $this->assertSame('MOD PM', $sun->notes);

        $this->assertSame('mc', $entry($this->bravo, '2026-09-30')->leave_type);
        $this->assertSame('ch', $entry($this->bravo, '2026-10-01')->leave_type);
        $this->assertSame(7, RosterEntry::where('roster_id', $roster->id)->where('employee_id', $this->charlie->id)->count());

        // Rows keep the sheet's order.
        $this->assertLessThan(
            $entry($this->bravo, '2026-09-28')->sort_order,
            $entry($this->alpha, '2026-09-28')->sort_order
        );

        $this->assertSame('BIG EVENT', RosterDayRemark::where('roster_id', $roster->id)->sole()->remark_text);
        $this->assertFalse(RosterStation::where('outlet_id', $this->outlet->id)->where('name', 'MOD PM')->exists());

        // The manual match for ZULU is remembered for next week's sheet.
        $this->assertSame($this->charlie->id, RosterNameAlias::where('alias', 'ZULU')->value('employee_id'));
        $again = app(RosterPdfImport::class)->suggest([['name' => 'ZULU']], $this->outlet->id, $this->foh->id);
        $this->assertSame(['employee_id' => $this->charlie->id, 'how' => 'remembered', 'candidates' => []], $again[0]);
    }

    /**
     * Friday's re-upload after two shifts moved: the people on the sheet are
     * rewritten, somebody added to the draft by hand is left alone.
     */
    public function test_reimporting_replaces_only_the_people_on_the_sheet(): void
    {
        $user = $this->user();
        $this->modal($user)->set('pdf', $this->pdf())->set('assign.2', '')->call('import');

        $roster = Roster::withoutGlobalScopes()->sole();
        RosterEntry::create([
            'roster_id' => $roster->id, 'employee_id' => $this->charlie->id, 'day_date' => '2026-09-28',
            'shift_start' => '10:00', 'shift_end' => '18:00', 'rest_duration' => 60, 'sort_order' => 9,
        ]);

        $this->modal($user)
            ->set('pdf', $this->pdf(['OFF DAY', 'OFF DAY', 'OFF DAY', 'OFF DAY', 'OFF DAY', 'OFF DAY', 'OFF DAY']))
            ->set('assign.2', '')
            ->assertSee('A draft roster already exists')
            ->call('import');

        $this->assertSame(1, Roster::withoutGlobalScopes()->count());
        $alphaWeek = RosterEntry::where('roster_id', $roster->id)->where('employee_id', $this->alpha->id)->get();
        $this->assertCount(7, $alphaWeek);
        $this->assertTrue($alphaWeek->every->is_off_day);
        $this->assertSame(1, RosterEntry::where('roster_id', $roster->id)->where('employee_id', $this->charlie->id)->count());
    }

    public function test_an_approved_week_is_not_overwritten(): void
    {
        $user = $this->user();
        Roster::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id, 'section_id' => $this->foh->id,
            'week_start_date' => '2026-09-28', 'week_end_date' => '2026-10-04',
            'status' => Roster::STATUS_APPROVED, 'revision' => 1,
        ]);

        $this->modal($user)
            ->set('pdf', $this->pdf())
            ->assertSee('Revert it to Draft')
            ->call('import')
            ->assertNotDispatched('roster-pdf-imported');

        $this->assertSame(0, RosterEntry::count());
    }

    public function test_the_same_employee_on_two_rows_blocks_the_import(): void
    {
        $this->modal($this->user())
            ->set('pdf', $this->pdf())
            ->set('assign.2', (string) $this->alpha->id)
            ->assertSee('Also picked on another row')
            ->call('import')
            ->assertNotDispatched('roster-pdf-imported');

        $this->assertSame(0, Roster::withoutGlobalScopes()->count());
    }

    public function test_creating_a_roster_needs_roster_create(): void
    {
        $this->modal($this->user(['roster.edit']))
            ->set('pdf', $this->pdf())
            ->call('import')
            ->assertSet('error', 'You do not have permission to create rosters.');

        $this->assertSame(0, Roster::withoutGlobalScopes()->count());
    }

    public function test_a_file_that_is_not_a_pdf_is_turned_away_with_directions(): void
    {
        $this->modal($this->user())
            ->set('pdf', UploadedFile::fake()->create('roster.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->assertHasErrors('pdf')
            ->assertSee('Save as PDF');
    }

    public function test_name_scores(): void
    {
        $import = app(RosterPdfImport::class);

        $this->assertSame(100, $import->score('Charlie Tan', 'CHARLIE TAN'));
        $this->assertSame(80, $import->score('FARHAN', 'Muhammad Farhan bin Ahmad'));
        $this->assertSame(80, $import->score('AS-SYAFIQ', 'Muhammad As Syafiq'));
        $this->assertSame(80, $import->score('NURAINI', 'Nur Aini binti Ali'));
        $this->assertSame(70, $import->score('SITI AMINAH', 'Siti Nur Aminah'));
        $this->assertSame(40, $import->score('ANNE', 'Annesa Lim'));
        $this->assertSame(0, $import->score('ELMER', 'Mary Tan'));
    }
}
