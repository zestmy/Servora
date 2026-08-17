<?php

namespace Tests\Feature;

use App\Livewire\Staff\Home;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Clock app home screen opens with a greeting — face and FULL name,
 * before anything transactional. The avatar is the shared staff-avatar:
 * photo over initials from the PIN-session photo route, degrading to the
 * coloured disc when there is no photo on file.
 *
 * The full name is load-bearing, not a style choice: this shipped cutting
 * at the first space and greeted MOHD AFFANDY BIN ZULKARNAIN as "MOHD" —
 * names here are not reliably given-name-first, so picking the "first
 * name" out of one is guessing.
 */
class ClockStaffHomeGreetingTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name'      => 'Home Greeting Co',
            'slug'      => Str::slug('Home Greeting Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $company->id,
            'outlet_id'  => $outlet->id,
            // A Malay-pattern name, because the bug this pins was cutting
            // "MOHD AFFANDY BIN ZULKARNAIN" down to "MOHD".
            'name'       => 'Mohd Affandy Bin Zulkarnain',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        // A PIN session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->employee, 'email');
    }

    public function test_the_greeting_carries_the_photo_and_the_full_name(): void
    {
        $this->employee->update(['photo_path' => 'hr/photos/affandy.jpg']);

        $html = Livewire::test(Home::class)
            // The WHOLE name — "Mohd" alone is the bug, not a greeting.
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->assertSeeInOrder(['Good', 'Mohd Affandy Bin Zulkarnain'])
            ->html();

        $this->assertStringContainsString(route('clock.staff.photo', $this->employee->id), $html);
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $html = Livewire::test(Home::class)
            ->assertSee('Mohd Affandy Bin Zulkarnain')
            ->html();

        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->employee->id),
            $html,
            'No photo on file must mean no img at all — the disc is the avatar.'
        );
        $this->assertStringContainsString('MA', $html, 'The coloured disc carries the initials.');
    }
}
