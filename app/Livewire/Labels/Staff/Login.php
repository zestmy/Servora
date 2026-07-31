<?php

namespace App\Livewire\Labels\Staff;

use App\Models\Employee;
use App\Scopes\CompanyScope;
use App\Services\Labels\LabelStaffSession;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Staff sign-in: pick your name, then key in your PIN.
 *
 * Name first, PIN second — not PIN alone. PINs are hashed, so finding an
 * employee from a PIN alone would mean bcrypt-checking every employee in
 * the company on every attempt: slow by design, and it would force a fast
 * hash instead, which is exactly the wrong trade for a 4–6 digit secret.
 * Picking a name makes verification a single check. It is also how every
 * POS terminal works, so it needs no explaining.
 *
 * Only employees with a PIN are listed. A manager grants access by setting
 * one and revokes it by clearing it.
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

    /** Number pad. Submits itself once the PIN is long enough to be one. */
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

    public function submit(LabelStaffSession $session)
    {
        $employee = $this->employee();

        if (! $employee) {
            $this->error = 'Pick your name first.';

            return null;
        }

        $key = 'label-pin:' . $employee->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->error = 'Too many attempts. Wait '
                . RateLimiter::availableIn($key) . ' seconds.';
            $this->pin = '';

            return null;
        }

        if (! $employee->verifyLabelPin($this->pin)) {
            // Counted per employee: a shared kitchen tablet means one IP for
            // everyone, so throttling by IP would lock out the whole kitchen
            // because one person fumbled their PIN.
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            $this->error = 'Wrong PIN.';
            $this->pin   = '';

            return null;
        }

        RateLimiter::clear($key);
        $session->signIn($employee);

        return $this->redirectRoute('labels.staff.print', navigate: false);
    }

    public function render()
    {
        return view('livewire.labels.staff.login', [
            'employees' => $this->staffWithPins(),
            'selected'  => $this->employee(),
            'company'   => \App\Models\Company::find(app(LabelStaffSession::class)->companyId()),
        ])->layout('layouts.labels-staff', ['title' => 'Sign in']);
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
        $companyId = app(LabelStaffSession::class)->companyId();

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
