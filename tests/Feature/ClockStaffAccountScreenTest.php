<?php

namespace Tests\Feature;

use App\Livewire\Clock\Staff\Account;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Clock app's account screen shows who is signed in, face first — the
 * same shared staff-avatar as the labels account screen and the boards.
 * Photo over initials, so an employee with no photo on file still gets
 * their coloured disc, and the photo comes from the PIN-session route
 * rather than the manager's hr.view one. Mirrors LabelStaffPinScreenTest;
 * the sign-in switching behaviour on this screen is covered by
 * StaffEmailLoginTest.
 */
class ClockStaffAccountScreenTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::create([
            'name'      => 'Clock Account Co',
            'slug'      => Str::slug('Clock Account Co') . '-' . uniqid(),
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

    public function test_the_account_screen_shows_the_photo_when_there_is_one(): void
    {
        $this->employee->update(['photo_path' => 'hr/photos/aisyah.jpg']);

        Livewire::test(Account::class)
            ->assertSee('Aisyah Rahman')
            ->assertSee(route('clock.staff.photo', $this->employee->id));
    }

    public function test_no_photo_on_file_means_the_initials_disc_not_a_broken_image(): void
    {
        $html = Livewire::test(Account::class)
            ->assertSee('Aisyah Rahman')
            ->html();

        $this->assertStringContainsString('AR', $html, 'The coloured disc carries the initials.');
        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->employee->id),
            $html,
            'No photo on file must mean no img at all — the disc is the avatar.'
        );
    }
}
