<?php

namespace App\Livewire\Staff;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Scopes\CompanyScope;
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

    public function mount(): void
    {
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
     * Outlets with somebody who can actually sign in.
     *
     * An outlet whose staff have no PINs is a dead end — offering it would
     * lead to an empty name list and no way to tell why.
     */
    public function outlets()
    {
        $companyId = $this->companyId();

        if (! $companyId) {
            return collect();
        }

        return Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('id', function ($query) use ($companyId) {
                $query->select('outlet_id')
                    ->from('employees')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('label_pin');
            })
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
            'outlets'  => $this->outlets(),
            'employees' => $this->employees(),
            'selected' => $this->employee(),
            'company'  => Company::find($this->companyId()),
            'tagline'  => $this->tagline(),
            'iconPath' => $this->iconPath(),
        ])->layout($this->layoutName(), ['title' => 'Sign in']);
    }
}
