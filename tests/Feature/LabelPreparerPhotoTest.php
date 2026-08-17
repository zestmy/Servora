<?php

namespace Tests\Feature;

use App\Livewire\Labels\PrintScreen;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LabelPrinter;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The "Prepared by" picker wears the chosen preparer's face — served by its
 * own labels.print-gated route, because the print screens' users hold
 * labels.print and not necessarily hr.view, and handing them the HR photo
 * route would fill the picker with 403s wearing broken-image icons.
 *
 * The fence mirrors the picker's own list: active staff at an outlet the
 * viewer can reach. An employee across the outlet boundary is the case that
 * proves it — their name never appears in this viewer's picker, so their
 * face must not be fetchable either.
 */
class LabelPreparerPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $mine;
    private Outlet $theirs;
    private Employee $myCook;
    private Employee $theirCook;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::create([
            'name' => 'Preparer Faces Co', 'slug' => Str::slug('Preparer Faces Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->mine   = $this->outlet('KLCC', 'KLCC');
        $this->theirs = $this->outlet('PUTRAJAYA', 'PJY');

        $this->myCook    = $this->cook('AISYAH RAHMAN', $this->mine, 'hr/photos/aisyah.jpg');
        $this->theirCook = $this->cook('BEN OOI', $this->theirs, 'hr/photos/ben.jpg');
    }

    private function outlet(string $name, string $code): Outlet
    {
        return Outlet::create([
            'company_id' => $this->company->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);
    }

    private function cook(string $name, Outlet $outlet, string $photo): Employee
    {
        Storage::disk('local')->put($photo, 'jpeg-bytes');

        return Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id,
            'name' => $name, 'photo_path' => $photo, 'is_active' => true,
        ]);
    }

    private function user(array $abilities = ['labels.print'], ?array $outletIds = null): User
    {
        $u = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => false,
        ]);
        $u->companies()->syncWithoutDetaching([$this->company->id]);
        $u->outlets()->sync($outletIds ?? [$this->mine->id]);

        setPermissionsTeamId($this->company->id);
        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        if ($abilities) {
            $u->givePermissionTo($abilities);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u;
    }

    public function test_a_preparer_at_my_outlet_is_served(): void
    {
        $this->actingAs($this->user())
            ->get(route('labels.preparer.photo', $this->myCook->id))
            ->assertOk();
    }

    /** Their name never appears in my picker, so their face must not either. */
    public function test_an_outlet_i_cannot_reach_is_404(): void
    {
        $this->actingAs($this->user())
            ->get(route('labels.preparer.photo', $this->theirCook->id))
            ->assertNotFound();
    }

    public function test_without_labels_print_the_route_is_403(): void
    {
        Permission::findOrCreate('labels.print', 'web');

        $this->actingAs($this->user(abilities: []))
            ->get(route('labels.preparer.photo', $this->myCook->id))
            ->assertForbidden();
    }

    public function test_another_companys_employee_is_404(): void
    {
        $rival = Company::create([
            'name' => 'Rival Co', 'slug' => Str::slug('Rival Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $rivalOutlet = Outlet::create([
            'company_id' => $rival->id, 'name' => 'Rival Main', 'code' => 'RVL', 'is_active' => true,
        ]);
        $rivalCook = Employee::create([
            'company_id' => $rival->id, 'outlet_id' => $rivalOutlet->id,
            'name' => 'RIVAL COOK', 'photo_path' => 'hr/photos/rival.jpg', 'is_active' => true,
        ]);
        Storage::disk('local')->put('hr/photos/rival.jpg', 'jpeg-bytes');

        $this->actingAs($this->user())
            ->get(route('labels.preparer.photo', $rivalCook->id))
            ->assertNotFound();
    }

    // ── The picker itself ─────────────────────────────────────────────────

    public function test_choosing_a_preparer_shows_their_face_from_the_labels_route(): void
    {
        LabelPrinter::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->mine->id, 'name' => 'KLCC DELI',
            'width_mm' => 50, 'height_mm' => 25, 'is_active' => true, 'driver' => 'browser',
        ]);

        $html = Livewire::actingAs($this->user())->test(PrintScreen::class)
            ->set('employeeId', $this->myCook->id)
            ->assertSee('AISYAH RAHMAN')
            ->html();

        $this->assertStringContainsString(
            route('labels.preparer.photo', $this->myCook->id),
            $html,
            'The face confirms the right row was picked — this name goes on every label.'
        );
        $this->assertStringNotContainsString(
            route('hr.employees.photo', $this->myCook->id),
            $html,
            'The HR route would 403 for a labels.print-only user.'
        );
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $this->myCook->forceFill(['photo_path' => null])->save();

        LabelPrinter::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->mine->id, 'name' => 'KLCC DELI',
            'width_mm' => 50, 'height_mm' => 25, 'is_active' => true, 'driver' => 'browser',
        ]);

        $html = Livewire::actingAs($this->user())->test(PrintScreen::class)
            ->set('employeeId', $this->myCook->id)
            ->html();

        $this->assertStringNotContainsString('/labels/preparer/', $html);
        $this->assertStringContainsString('AR', $html, 'The coloured disc carries the initials.');
    }
}
