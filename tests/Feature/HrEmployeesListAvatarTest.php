<?php

namespace Tests\Feature;

use App\Livewire\Hr\Employees;
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
 * The HR employees screen wears faces everywhere a person is named: the main
 * list rows, and the food-handler "not taken" chips. The chips are the case
 * this test exists for — their query selects explicit columns, and a select
 * that forgets photo_path does not error, it just renders every chip as
 * initials while the row beside it shows a face. The avatar component falls
 * back silently, so only an assertion notices.
 */
class HrEmployeesListAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $user;
    private Employee $withPhoto;
    private Employee $withoutPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Faces HR Co', 'slug' => Str::slug('Faces HR Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        // Neither has taken food handler training, so both appear as chips
        // under the compliance card as well as rows in the list.
        $this->withPhoto = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'AISYAH RAHMAN', 'photo_path' => 'hr/photos/aisyah.jpg',
            'is_active' => true, 'join_date' => '2025-01-01',
        ]);

        $this->withoutPhoto = Employee::create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'name' => 'BEN OOI',
            'is_active' => true, 'join_date' => '2025-01-01',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        Permission::findOrCreate('hr.view', 'web');
        $this->user->givePermissionTo(['hr.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_the_list_row_and_the_food_handler_chip_both_carry_the_photo(): void
    {
        $html = Livewire::actingAs($this->user)->test(Employees::class)
            ->assertSee('AISYAH RAHMAN')
            ->html();

        $photoUrl = route('hr.employees.photo', $this->withPhoto->id);

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, $photoUrl),
            'The face belongs on the list row and the compliance panels, not just one of them.'
        );

        /*
         * The chip is asserted INSIDE its own markup, not by counting: the
         * list row and the compliance card also carry the photo, so a global
         * count stays green while the chip quietly renders initials — which
         * is exactly the bug (a select() without photo_path) this pins.
         */
        $chipAt = strpos($html, 'wire:key="fh-' . $this->withPhoto->id . '"');
        $this->assertNotFalse($chipAt, 'The food-handler chip renders for uncertified staff.');
        $this->assertStringContainsString(
            $photoUrl,
            substr($html, $chipAt, 800),
            'The chip itself wears the face — its query must select photo_path.'
        );
    }

    public function test_no_photo_on_file_means_initials_not_a_broken_image(): void
    {
        $html = Livewire::actingAs($this->user)->test(Employees::class)
            ->assertSee('BEN OOI')
            ->html();

        $this->assertStringNotContainsString(
            route('hr.employees.photo', $this->withoutPhoto->id),
            $html,
            'No photo on file must mean no img at all — the initials are the avatar.'
        );
        $this->assertStringContainsString('BE', $html, 'Two letters off the front of the name.');
    }
}
