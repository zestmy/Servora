<?php

namespace Tests\Feature;

use App\Http\Controllers\Lms\StaffHandoffController;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LmsUser;
use App\Models\Outlet;
use App\Services\Staff\StaffSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opening the SOP library from the Staff Portal, on the staff PIN session
 * rather than a separate LMS login. See Lms\StaffHandoffController.
 */
class LmsStaffPinHandoffTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Handoff Co', 'slug' => Str::slug('Handoff Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'KLCC', 'code' => 'KLCC', 'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah@example.test',
            'is_active'  => true,
        ]);
    }

    private function signInToStaffPortal(): void
    {
        // A staff session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $this->company->id]);
        app(StaffSession::class)->signIn($this->employee, 'email');
    }

    public function test_a_staff_session_opens_the_sop_library_with_a_new_approved_account(): void
    {
        $this->signInToStaffPortal();

        $this->get(route('clock.staff.lms'))->assertRedirect(route('lms.dashboard'));

        $account = LmsUser::sole();
        $this->assertSame($this->employee->id, (int) $account->employee_id);
        $this->assertSame('approved', $account->status);
        $this->assertSame($this->outlet->id, (int) $account->outlet_id, 'SOP access follows the employee\'s outlet.');
        $this->assertNull($account->password, 'The PIN is the credential; there is no password to guess.');
        $this->assertTrue(Auth::guard('lms')->check());
        $this->assertSame($account->id, Auth::guard('lms')->id());
    }

    public function test_opening_it_again_reuses_the_same_account(): void
    {
        $this->signInToStaffPortal();

        $this->get(route('clock.staff.lms'));
        $this->get(route('clock.staff.lms'));

        $this->assertSame(1, LmsUser::count());
    }

    public function test_an_existing_registration_with_the_employees_email_is_linked_not_duplicated(): void
    {
        $registered = LmsUser::create([
            'company_id' => $this->company->id, 'name' => 'Aisyah', 'email' => 'aisyah@example.test',
            'password' => 'secret123', 'status' => 'pending',
        ]);

        $this->signInToStaffPortal();
        $this->get(route('clock.staff.lms'))->assertRedirect(route('lms.dashboard'));

        $this->assertSame(1, LmsUser::count());
        $registered->refresh();
        $this->assertSame($this->employee->id, (int) $registered->employee_id);
        $this->assertSame('approved', $registered->status, 'A waiting registration is let through by the staff session.');
        $this->assertSame($registered->id, Auth::guard('lms')->id());
    }

    public function test_a_rejected_account_stays_rejected(): void
    {
        LmsUser::create([
            'company_id' => $this->company->id, 'employee_id' => $this->employee->id,
            'name' => 'Aisyah', 'status' => 'rejected',
        ]);

        $this->signInToStaffPortal();

        $this->get(route('clock.staff.lms'))
            ->assertRedirect(route('clock.staff.home'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::guard('lms')->check());
    }

    public function test_without_a_staff_session_it_asks_for_the_pin_first(): void
    {
        session(['subdomain_company_id' => $this->company->id]);

        $this->get(route('clock.staff.lms'))->assertRedirect(route('clock.staff.login'));
        $this->assertSame(0, LmsUser::count());
    }

    public function test_signing_out_of_the_lms_returns_to_the_staff_portal_still_signed_in(): void
    {
        $this->signInToStaffPortal();
        $this->get(route('clock.staff.lms'));

        $this->post(route('lms.logout'))->assertRedirect(route('clock.staff.home'));

        $this->assertFalse(Auth::guard('lms')->check());
        $this->assertTrue(
            app(StaffSession::class)->check($this->company->id),
            'Closing the SOP library must not sign the employee out of clock-in.'
        );
    }

    public function test_signing_out_of_the_staff_portal_ends_the_lms_session_too(): void
    {
        $this->signInToStaffPortal();
        $this->get(route('clock.staff.lms'));

        app(StaffSession::class)->signOut();

        $this->assertFalse(Auth::guard('lms')->check());
    }

    public function test_the_lms_session_ends_when_the_staff_session_goes_stale(): void
    {
        $this->signInToStaffPortal();
        $this->get(route('clock.staff.lms'));

        $this->employee->update(['is_active' => false]);

        $this->get(route('lms.dashboard'))->assertRedirect(route('clock.staff.login'));

        $this->assertFalse(Auth::guard('lms')->check());
        $this->assertNull(session(StaffHandoffController::SESSION_KEY));
    }

    public function test_the_staff_home_links_to_the_sop_library(): void
    {
        $this->signInToStaffPortal();

        $this->get(route('clock.staff.home'))
            ->assertOk()
            ->assertSee(route('clock.staff.lms'))
            ->assertSee('SOP Library');
    }

    public function test_the_lms_login_page_offers_the_staff_pin(): void
    {
        $this->get(route('lms.login', $this->company->slug))
            ->assertOk()
            ->assertSee('Sign in with staff PIN');
    }
}
