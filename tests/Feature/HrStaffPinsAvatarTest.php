<?php

namespace Tests\Feature;

use App\Livewire\Hr\StaffPins;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Staff PINs list wears faces — but only for a viewer who can actually
 * fetch them. The screen is gated on staff.pins; the photo route is gated on
 * hr.view, and the two abilities do not have to travel together. Handing the
 * <img> to a viewer without hr.view fills the list with 403s wearing
 * broken-image icons, which is exactly the trap the avatar components'
 * documentation warns about — so the photo renders only when the viewer
 * holds hr.view, and falls back to the initials everyone always saw.
 */
class HrStaffPinsAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $withPhoto;
    private Employee $withoutPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Pins Faces Co', 'slug' => Str::slug('Pins Faces Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->withPhoto = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH RAHMAN', 'photo_path' => 'hr/photos/aisyah.jpg', 'is_active' => true,
        ]);

        $this->withoutPhoto = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'BEN OOI', 'is_active' => true,
        ]);
    }

    private function manager(array $abilities): User
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $user->companies()->syncWithoutDetaching([$this->company->id]);
        $user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        foreach ($abilities as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_a_viewer_with_hr_view_sees_the_photo(): void
    {
        $html = Livewire::actingAs($this->manager(['staff.pins', 'hr.view']))
            ->test(StaffPins::class)
            ->assertSee('AISYAH RAHMAN')
            ->html();

        $this->assertStringContainsString(route('hr.employees.photo', $this->withPhoto->id), $html);

        // No photo on file still means initials, never a broken image.
        $this->assertStringNotContainsString(route('hr.employees.photo', $this->withoutPhoto->id), $html);
        $this->assertStringContainsString('BE', $html);
    }

    /**
     * staff.pins without hr.view: the photo route would 403, so the img must
     * never be rendered — initials for everyone, same as before the faces.
     */
    public function test_a_viewer_without_hr_view_gets_initials_not_403_images(): void
    {
        $html = Livewire::actingAs($this->manager(['staff.pins']))
            ->test(StaffPins::class)
            ->assertSee('AISYAH RAHMAN')
            ->html();

        $this->assertStringNotContainsString(
            route('hr.employees.photo', $this->withPhoto->id),
            $html,
            'A photo the viewer cannot fetch is a 403 wearing a broken-image icon.'
        );
        $this->assertStringContainsString('AI', $html, 'The initials disc stands in.');
    }
}
