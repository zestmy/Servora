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
 * The Clock app home screen opens with a greeting — face and first name,
 * before anything transactional. The avatar is the shared staff-avatar:
 * photo over initials from the PIN-session photo route, degrading to the
 * coloured disc when there is no photo on file.
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
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        // A PIN session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->employee, 'email');
    }

    public function test_the_greeting_carries_the_photo_and_the_first_name(): void
    {
        $this->employee->update(['photo_path' => 'hr/photos/aisyah.jpg']);

        $html = Livewire::test(Home::class)
            // First name only — a greeting that reads like a payroll record
            // isn't one.
            ->assertSee('Aisyah')
            ->assertSeeInOrder(['Good', 'Aisyah'])
            ->html();

        $this->assertStringContainsString(route('clock.staff.photo', $this->employee->id), $html);
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $html = Livewire::test(Home::class)
            ->assertSee('Aisyah')
            ->html();

        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->employee->id),
            $html,
            'No photo on file must mean no img at all — the disc is the avatar.'
        );
        $this->assertStringContainsString('AR', $html, 'The coloured disc carries the initials.');
    }
}
