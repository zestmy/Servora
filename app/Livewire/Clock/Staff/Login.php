<?php

namespace App\Livewire\Clock\Staff;

use App\Http\Middleware\ClockStaffAuthenticate;
use App\Models\Company;
use App\Models\Employee;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Staff sign-in: pick your name, then key in your PIN.
 *
 * The same staff PIN and the same session as the labels app — signing in on
 * one signs you in on both. See StaffSession for why that is deliberate, and
 * Labels\Staff\Login for why the name is picked before the PIN rather than
 * the PIN alone identifying somebody.
 */
class Login extends Component
{
    public ?int $employeeId = null;

    public string $pin = '';

    public string $error = '';

    /** Attempts allowed before a name is locked out briefly. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 60;

    public function selectEmployee(int $id): void
    {
        $this->employeeId = $id;
        $this->pin        = '';
        $this->error      = '';
    }

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

        // Shares the labels app's rate limit key on purpose: it is one PIN,
        // and letting someone get five guesses per app would double the
        // budget for free.
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
     * and inside the clock app, so it can never become an open redirect.
     */
    private function destination(): string
    {
        $intended = session()->pull(ClockStaffAuthenticate::INTENDED_KEY);
        $fallback = route('clock.staff.punch');

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

    public function render()
    {
        return view('livewire.clock.staff.login', [
            'employees' => $this->staffWithPins(),
            'selected'  => $this->employee(),
            'company'   => Company::find(app(StaffSession::class)->companyId()),
        ])->layout('layouts.clock-staff', ['title' => 'Sign in']);
    }

    private function employee(): ?Employee
    {
        if (! $this->employeeId) {
            return null;
        }

        return $this->staffWithPins()->firstWhere('id', $this->employeeId);
    }

    /**
     * Only this subdomain's company, only active staff, only those a manager
     * has given a PIN.
     */
    private function staffWithPins()
    {
        $companyId = app(StaffSession::class)->companyId();

        if (! $companyId) {
            return collect();
        }

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('label_pin')
            ->orderBy('name')
            ->get();
    }
}
