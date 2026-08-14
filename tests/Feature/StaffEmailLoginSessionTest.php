<?php

namespace Tests\Feature;

use App\Livewire\Clock\Staff\Login as StaffLoginScreen;
use App\Mail\Hr\StaffLoginCodeMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reported: signing in with an emailed code lands you back on the login page.
 *
 * The component said it worked and the session said it worked; what nobody had
 * exercised was the NEXT request — the middleware that every staff screen sits
 * behind. This walks the real sequence.
 */
class StaffEmailLoginSessionTest extends TestCase
{
    use RefreshDatabase;

    private function signInByEmail(bool $withPin)
    {
        Mail::fake();

        $company = Company::create([
            'name' => 'Session Co', 'slug' => Str::slug('Session Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);
        $outlet = Outlet::create([
            'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id, 'outlet_id' => $outlet->id,
            'name' => 'SAM', 'email' => 'sam@example.test', 'is_active' => true,
        ]);

        if ($withPin) {
            $employee->setLabelPin('4821');
        }

        session(['subdomain_company_id' => $company->id]);

        return Livewire::test(StaffLoginScreen::class)
            ->call('useEmail')
            ->set('loginEmail', 'sam@example.test')
            ->call('sendCode')
            ->set('emailCode', Mail::sent(StaffLoginCodeMail::class)->last()->code)
            ->call('submitCode')
            ->assertSet('error', '');
    }

    private function assertGuardedPageOpens(): void
    {
        $response = $this->get(route('clock.staff.punch'));

        $this->assertFalse(
            $response->isRedirect(route('clock.staff.login')),
            'Signed in, then bounced straight back to the login page.'
        );
        $response->assertOk();
    }

    public function test_after_an_email_sign_in_a_guarded_page_opens(): void
    {
        $this->signInByEmail(withPin: false);

        $this->assertGuardedPageOpens();
    }

    public function test_reloading_the_login_page_while_signed_in_does_not_ask_again(): void
    {
        $this->signInByEmail(withPin: true);

        $response = $this->get(route('clock.staff.login'));

        $this->assertTrue(
            $response->isRedirect(),
            'Somebody already signed in must be taken into the app, not shown the door again.'
        );
    }

    public function test_a_pin_holder_signing_in_by_email_goes_straight_in(): void
    {
        $this->signInByEmail(withPin: true);

        $this->assertGuardedPageOpens();
    }

    /**
     * Launching the app never lands on the clock, however old the installed
     * icon is.
     *
     * Reported twice as "the Staff Portal still lands on Clock-In" after the
     * route, the post-login fallback and the manifest had all been moved to
     * Home. The access log had it:
     *
     *     GET /staff/clock  302 (no referrer)  → /staff/login
     *     GET /staff/clock  200  referrer=/staff/login
     *
     * An app installed before the move still launches the old start_url, iOS
     * never refetches an installed web app's manifest, and the intended-URL
     * machinery faithfully returned them to where the launch had landed.
     */
    public function test_a_launch_at_the_old_start_url_still_lands_on_home(): void
    {
        // The launch: bounced to sign-in, which records where it was going.
        $this->get(route('clock.staff.punch'))
            ->assertRedirect(route('clock.staff.login'));

        $component = $this->signInByEmail(withPin: true);

        $component->assertRedirect(route('clock.staff.home'));
    }

    /**
     * A genuine deep link is still honoured — which is the whole reason the
     * intended URL exists, and what makes the rule above narrow rather than a
     * blanket "always go home".
     */
    public function test_a_real_deep_link_still_returns_after_sign_in(): void
    {
        $this->get(route('clock.staff.payslips'))
            ->assertRedirect(route('clock.staff.login'));

        $this->signInByEmail(withPin: true)
            ->assertRedirect(route('clock.staff.payslips'));
    }
}
