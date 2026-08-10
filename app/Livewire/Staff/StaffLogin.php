<?php

namespace App\Livewire\Staff;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Scopes\CompanyScope;
use App\Services\Staff\EmailLoginCode;
use App\Services\Staff\StaffSession;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Shared sign-in for every staff app: pick your outlet, pick your name, key
 * in your PIN.
 *
 * One base class because the labels app and the clock app ask the same
 * question of the same person with the same PIN. They had been two copies of
 * one screen, which is how a change lands in one and not the other.
 *
 * OUTLET FIRST, and this is the point of the screen. A company with two
 * branches lists everybody from both, so a cook at KLCC scrolls past
 * colleagues from Putrajaya to find themselves — and two people with similar
 * names in different outlets are indistinguishable in a flat list. Narrowing
 * by outlet makes the second dropdown short enough to be read rather than
 * searched.
 *
 * Name before PIN, never PIN alone: PINs are hashed, so finding an employee
 * from a PIN would mean bcrypt-checking every employee in the company on
 * every attempt — slow by design, and it would force a fast hash instead,
 * which is exactly the wrong trade for a 4–6 digit secret.
 */
abstract class StaffLogin extends Component
{
    public ?int $outletId = null;

    public ?int $employeeId = null;

    public string $pin = '';

    public string $error = '';

    /*
     * The second way in.
     *
     * 52 of 53 active employees have an email address on file and 7 have a PIN,
     * so most of the workforce could not reach the staff app at all — a manager
     * has to enrol each person by hand, and until they do, that person is
     * locked out of clocking in. A code sent to an address already on their
     * record proves the same thing a PIN does.
     *
     * 'pin' or 'email'. The PIN keypad stays the default: it is faster, it works
     * on the outlet tablet with no phone in hand, and it is what the people who
     * already have one expect to see.
     */
    public string $method = 'pin';

    public string $loginEmail = '';

    public string $emailCode = '';

    /** Set once a code has gone out, so the screen can move to the code step. */
    public bool $codeSent = false;

    public string $notice = '';

    /**
     * Offered immediately after an email sign-in, and only then.
     *
     * Turning off your own PIN is a change to how you get in, so it asks to be
     * made by the person themselves — but from a screen where they have just
     * proved they are that person. Offering it on the way IN, to anyone who can
     * name a colleague, would let a stranger lock somebody out of clocking in
     * without ever signing in as them.
     */
    public bool $offerPinSwitch = false;

    /** Attempts allowed before a name is locked out briefly. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    /**
     * Remembers which outlet this device signs in at.
     *
     * A tablet lives on one counter. Without this, every person signing in
     * re-picks the same branch all day — and the session cookie is exactly
     * the right scope for "this device", since it is per browser and
     * survives sign-out.
     */
    private const OUTLET_KEY = 'staff_login_outlet';

    /** Where a bounced request wanted to go, per app. */
    abstract protected function intendedKey(): string;

    /** Landing route when there is no remembered destination. */
    abstract protected function fallbackRoute(): string;

    abstract protected function layoutName(): string;

    /** Shown under the company name, e.g. "Staff attendance". */
    abstract protected function tagline(): string;

    /** SVG path for the fallback mark, when a company has no logo. */
    abstract protected function iconPath(): string;

    public function mount()
    {
        /*
         * Already signed in? Go in.
         *
         * Reported as "the email login sends me back to the login page". It
         * does not — this screen simply never asked whether the person was
         * already through the door, so a reload, a back button or reopening the
         * installed app showed a sign-in form to somebody holding a live
         * session, with nothing on it saying so.
         *
         * The email route made it certain rather than occasional: it parks you
         * HERE, signed in, to ask about your PIN, so any reload during that
         * question stranded you. The PIN route had the same hole and hid it,
         * because it redirects the instant the last digit lands.
         */
        if (app(StaffSession::class)->check($this->companyId())) {
            return $this->redirect($this->destination(), navigate: false);
        }

        $outlets = $this->outlets();

        $remembered = session(self::OUTLET_KEY);

        if ($remembered && $outlets->contains('id', (int) $remembered)) {
            $this->outletId = (int) $remembered;
        } elseif ($outlets->count() === 1) {
            // One outlet is not a choice. Asking would be a tap that can only
            // ever have one answer.
            $this->outletId = (int) $outlets->first()->id;
        }
    }

    public function updatedOutletId(): void
    {
        $this->employeeId = null;
        $this->pin        = '';
        $this->error      = '';

        if ($this->outletId) {
            session([self::OUTLET_KEY => (int) $this->outletId]);
        }
    }

    public function updatedEmployeeId(): void
    {
        $this->pin   = '';
        $this->error = '';
    }

    /** "Not you?" — back to the name dropdown. */
    public function back(): void
    {
        $this->employeeId = null;
        $this->pin        = '';
        $this->error      = '';
    }

    // ── Signing in by email ───────────────────────────────────────────────

    public function useEmail(): void
    {
        $this->method   = 'email';
        $this->error    = '';
        $this->notice   = '';
        $this->pin      = '';
        $this->codeSent = false;
    }

    public function usePin(): void
    {
        $this->method    = 'pin';
        $this->error     = '';
        $this->notice    = '';
        $this->emailCode = '';
        $this->codeSent  = false;
    }

    /**
     * Send a code — and say the same thing whether or not the address is ours.
     *
     * An honest "no such employee" here would turn the sign-in screen into a
     * way of asking who works at a company, one address at a time.
     */
    public function sendCode(EmailLoginCode $codes): void
    {
        $this->error  = '';
        $this->notice = '';

        if (trim($this->loginEmail) === '') {
            $this->error = 'Enter your email address.';

            return;
        }

        $companyId = $this->companyId();

        if (! $companyId) {
            $this->error = 'This link is missing its company. Ask your manager for the right address.';

            return;
        }

        $employee = $codes->employeeFor($companyId, $this->loginEmail);

        if (! $codes->send($employee, $this->loginEmail, request()->ip())) {
            $this->error = 'Too many codes requested. Wait a few minutes and try again.';

            return;
        }

        $this->codeSent  = true;
        $this->emailCode = '';
        $this->notice    = 'If that address is on file, a code is on its way. It lasts '
            . EmailLoginCode::TTL_MINUTES . ' minutes.';
    }

    public function submitCode(EmailLoginCode $codes, StaffSession $session)
    {
        $this->error = '';

        $companyId = $this->companyId();
        $employee  = $companyId ? $codes->employeeFor($companyId, $this->loginEmail) : null;

        // Wrong code and unknown address are one message: the pair of them would
        // otherwise answer the question the send step refused to.
        if (! $employee || ! $codes->verify($employee, $this->emailCode)) {
            $this->error     = 'That code is wrong or has expired. Ask for a new one.';
            $this->emailCode = '';

            return null;
        }

        $session->signIn($employee, 'email');

        // Stay on the screen just long enough to offer the switch, but only to
        // someone who actually has a PIN to turn off.
        if ($employee->hasLabelPin() && ! $employee->pin_login_disabled_at) {
            $this->employeeId     = $employee->id;
            $this->offerPinSwitch = true;
            $this->notice         = 'Signed in.';

            return null;
        }

        return $this->redirect($this->destination(), navigate: false);
    }

    /**
     * The employee's own decision, made having just proved who they are.
     *
     * Their PIN is left in place rather than cleared: to a manager, somebody who
     * chose email must not look like somebody who was never enrolled, and
     * changing their mind should not need a credential reissued.
     */
    public function disablePinLogin()
    {
        $employee = \App\Models\Employee::withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->find($this->employeeId);

        if (! $employee || ! $this->offerPinSwitch) {
            return $this->redirect($this->destination(), navigate: false);
        }

        $employee->forceFill(['pin_login_disabled_at' => now()])->save();

        return $this->redirect($this->destination(), navigate: false);
    }

    public function keepPinLogin()
    {
        return $this->redirect($this->destination(), navigate: false);
    }

    /** Number pad. */
    public function press(string $digit): void
    {
        if (strlen($this->pin) >= 6) {
            return;
        }

        $this->pin  .= $digit;
        $this->error = '';
    }

    public function backspace(): void
    {
        $this->pin   = substr($this->pin, 0, -1);
        $this->error = '';
    }

    public function submit(StaffSession $session)
    {
        $employee = $this->employee();

        if (! $employee) {
            $this->error = 'Pick your name first.';

            return null;
        }

        // One key across both apps: it is one PIN, and letting somebody get
        // five guesses per app would double the budget for free.
        $key = 'label-pin:' . $employee->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->error = 'Too many attempts. Wait '
                . RateLimiter::availableIn($key) . ' seconds.';
            $this->pin = '';

            return null;
        }

        if (! $employee->verifyLabelPin($this->pin)) {
            // Counted per employee, not per IP: a shared outlet tablet means
            // one IP for everyone, so throttling by IP would lock out the
            // whole floor because one person fumbled their PIN.
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            $this->error = 'Wrong PIN.';
            $this->pin   = '';

            return null;
        }

        RateLimiter::clear($key);
        $session->signIn($employee);

        return $this->redirect($this->destination(), navigate: false);
    }

    /**
     * Where to land after signing in.
     *
     * Validated rather than trusted: the stored URL must be on this same host
     * AND inside this app's own path, so it can never become an open
     * redirect. Host alone is not enough — url()->previous() falls back to
     * the app root, and "/" passes a host check while navigating clean out
     * of the staff app.
     */
    private function destination(): string
    {
        $intended = session()->pull($this->intendedKey());
        $fallback = route($this->fallbackRoute());

        if (! $intended) {
            return $fallback;
        }

        $target = parse_url($intended);
        $here   = parse_url($fallback);

        if (($target['host'] ?? null) !== ($here['host'] ?? null)) {
            return $fallback;
        }

        $base = rtrim($here['path'] ?? '/', '/');
        $path = $target['path'] ?? '';

        if ($base !== '' && ! str_starts_with($path, $base)) {
            return $fallback;
        }

        return $intended;
    }

    protected function companyId(): ?int
    {
        return app(StaffSession::class)->companyId();
    }

    /**
     * Every active outlet in the company.
     *
     * This used to list only outlets that already had PIN holders, which
     * made the selector vanish for a company that had set up PINs at one
     * branch — the dropdown appeared to be missing rather than narrowed. An
     * outlet with nobody enrolled now shows and says so, which is a far
     * easier thing to act on than an absent control.
     */
    public function outlets()
    {
        $companyId = $this->companyId();

        if (! $companyId) {
            return collect();
        }

        return Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Staff at the chosen outlet who have been given a PIN.
     *
     * CompanyScope is dropped and company_id matched by hand: nobody is
     * authenticated here, so the scope resolves from the subdomain at best
     * and matches nothing at worst.
     */
    public function employees()
    {
        $companyId = $this->companyId();

        if (! $companyId || ! $this->outletId) {
            return collect();
        }

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('outlet_id', $this->outletId)
            ->where('is_active', true)
            ->whereNotNull('label_pin')
            // Somebody who has moved to email is not offered a keypad they have
            // switched off — the name would be there and the PIN would refuse.
            ->whereNull('pin_login_disabled_at')
            ->orderBy('name')
            ->get();
    }

    /** The chosen employee, re-checked against the chosen outlet. */
    protected function employee(): ?Employee
    {
        if (! $this->employeeId) {
            return null;
        }

        return $this->employees()->firstWhere('id', $this->employeeId);
    }

    public function render()
    {
        return view('livewire.staff.login', [
            'outlets'   => $this->outlets(),
            'employees' => $this->employees(),
            'selected'  => $this->employee(),
            'company'   => Company::find($this->companyId()),
            'tagline'   => $this->tagline(),
            'iconPath'  => $this->iconPath(),
            'codeTtl'   => EmailLoginCode::TTL_MINUTES,
        ])->layout($this->layoutName(), ['title' => 'Sign in']);
    }
}
