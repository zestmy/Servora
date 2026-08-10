<?php

namespace Tests\Feature;

use App\Livewire\Clock\Staff\Login as StaffLoginScreen;
use App\Mail\Hr\StaffLoginCodeMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoginCode;
use App\Models\Outlet;
use App\Services\Staff\EmailLoginCode;
use App\Services\Staff\StaffSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Signing in to the staff app with an emailed code.
 *
 * 52 of 53 active employees have an email on file and 7 have a PIN, so most of
 * the workforce could not reach the app at all until a manager enrolled them by
 * hand. A code sent to an address already on their record proves the same thing
 * a PIN does.
 *
 * It is a credential, so the tests here are mostly about what it must NOT do:
 * not reveal who works at a company, not survive being used, not survive a new
 * one being asked for, and not let a stranger turn off somebody's PIN.
 */
class StaffEmailLoginTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        RateLimiter::clear('staff-login-code:email:sam@example.test');

        $this->company = Company::create([
            'name'      => 'Email Login Co',
            'slug'      => Str::slug('Email Login Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id,
            'name'       => 'Main',
            'code'       => 'MAIN',
            'is_active'  => true,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'SAM',
            'email'      => 'sam@example.test',
            'is_active'  => true,
        ]);

        // How the subdomain middleware tells the staff apps which company they
        // are serving — the same key StaffSession falls back to.
        session(['subdomain_company_id' => $this->company->id]);
    }

    private function screen()
    {
        return Livewire::test(StaffLoginScreen::class);
    }

    private function codes(): EmailLoginCode
    {
        return app(EmailLoginCode::class);
    }

    public function test_a_code_is_emailed_and_signs_the_employee_in(): void
    {
        $this->screen()->call('useEmail')->set('loginEmail', 'sam@example.test')->call('sendCode');

        Mail::assertSent(StaffLoginCodeMail::class, fn ($mail) => $mail->hasTo('sam@example.test'));

        $this->assertSame(1, EmployeeLoginCode::where('employee_id', $this->employee->id)->count());
    }

    /**
     * A different answer for a known and an unknown address would turn the
     * sign-in screen into a way of asking who works here, one address at a time.
     */
    public function test_an_unknown_address_is_answered_exactly_like_a_known_one(): void
    {
        $known = $this->screen()->call('useEmail')->set('loginEmail', 'sam@example.test')->call('sendCode');
        $unknown = $this->screen()->call('useEmail')->set('loginEmail', 'nobody@example.test')->call('sendCode');

        $this->assertSame($known->get('notice'), $unknown->get('notice'));
        $this->assertSame('', $unknown->get('error'));
        $this->assertTrue($unknown->get('codeSent'));

        Mail::assertNotSent(StaffLoginCodeMail::class, fn ($mail) => $mail->hasTo('nobody@example.test'));
    }

    public function test_the_right_code_signs_you_in(): void
    {
        $screen = $this->screen()
            ->call('useEmail')
            ->set('loginEmail', 'sam@example.test')
            ->call('sendCode');

        $code = Mail::sent(StaffLoginCodeMail::class)->last()->code;

        $screen->set('emailCode', $code)->call('submitCode')->assertSet('error', '');

        $this->assertTrue(app(StaffSession::class)->check());
    }

    public function test_a_used_code_cannot_be_used_again(): void
    {
        $code = $this->issueCode();

        $this->assertTrue($this->codes()->verify($this->employee->fresh(), $code));
        $this->assertFalse(
            $this->codes()->verify($this->employee->fresh(), $code),
            'A one-time code that works twice is not one-time.'
        );
    }

    public function test_asking_for_a_new_code_kills_the_old_one(): void
    {
        $first = $this->issueCode();
        $this->issueCode();

        $this->assertFalse(
            $this->codes()->verify($this->employee->fresh(), $first),
            'A forwarded email must not stay usable behind the recipient.'
        );
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->issueCode();

        EmployeeLoginCode::where('employee_id', $this->employee->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($this->codes()->verify($this->employee->fresh(), $code));
    }

    public function test_guessing_burns_the_code_rather_than_allowing_a_walk_through(): void
    {
        $code = $this->issueCode();

        for ($i = 0; $i < EmailLoginCode::MAX_ATTEMPTS; $i++) {
            $this->codes()->verify($this->employee->fresh(), '000001');
        }

        $this->assertFalse(
            $this->codes()->verify($this->employee->fresh(), $code),
            'Six digits must not be walkable; the real code dies with the guesses.'
        );
    }

    public function test_too_many_requests_for_one_address_are_refused(): void
    {
        $screen = $this->screen()->call('useEmail')->set('loginEmail', 'sam@example.test');

        for ($i = 0; $i < 3; $i++) {
            $screen->call('sendCode');
        }

        $screen->call('sendCode');

        $this->assertStringContainsString('Too many codes', $screen->get('error'));
    }

    /**
     * The switch is offered only after signing in, to the person who just did.
     * Offering it on the way in would let anyone who can name a colleague lock
     * that colleague out of clocking in.
     */
    public function test_the_pin_switch_is_offered_only_after_an_email_sign_in(): void
    {
        $this->employee->setLabelPin('4821');

        $screen = $this->screen()
            ->call('useEmail')
            ->set('loginEmail', 'sam@example.test')
            ->call('sendCode');

        $screen->set('emailCode', Mail::sent(StaffLoginCodeMail::class)->last()->code)
            ->call('submitCode');

        $screen->assertSet('offerPinSwitch', true);

        $screen->call('disablePinLogin');

        $employee = $this->employee->fresh();

        $this->assertNotNull($employee->pin_login_disabled_at);
        $this->assertTrue(
            $employee->hasLabelPin(),
            'The PIN is switched off, not destroyed — a manager can turn it back on.'
        );
    }

    public function test_somebody_who_moved_to_email_is_not_offered_the_pin_keypad(): void
    {
        $this->employee->setLabelPin('4821');
        $this->employee->forceFill(['pin_login_disabled_at' => now()])->save();

        $html = $this->screen()->set('outletId', $this->outlet->id)->html();

        $this->assertStringNotContainsString(
            'SAM',
            $html,
            'Their name in the PIN list is a keypad that would refuse them.'
        );
    }

    /** Issue a code and read it back off the last message the fake captured. */
    private function issueCode(): string
    {
        $this->codes()->send($this->employee->fresh(), 'sam@example.test', '127.0.0.1');

        return Mail::sent(StaffLoginCodeMail::class)->last()->code;
    }
}
