<?php

namespace App\Services\Staff;

use App\Mail\Hr\StaffLoginCodeMail;
use App\Models\Employee;
use App\Models\EmployeeLoginCode;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issuing and checking the one-time codes staff sign in with by email.
 *
 * Kept out of the Livewire component because two apps mount that component and
 * neither should own a credential policy. Everything that decides whether
 * somebody gets in lives here, in one readable place.
 */
class EmailLoginCode
{
    /** Long enough to type from a phone, short enough to be read out. */
    public const LENGTH = 6;

    public const TTL_MINUTES = 10;

    /** Guesses allowed against a single code before it is burned. */
    public const MAX_ATTEMPTS = 5;

    /** Sends allowed per address, and per device, in a quarter of an hour. */
    private const SENDS_PER_EMAIL = 3;

    private const SENDS_PER_IP = 10;

    private const SEND_WINDOW = 900;

    /**
     * Find the employee an address belongs to.
     *
     * CompanyScope is dropped and company_id matched by hand: nobody is
     * authenticated at this point, so the scope resolves from the subdomain at
     * best and matches nothing at worst — the same reason the PIN path does it.
     */
    public function employeeFor(int $companyId, string $email): ?Employee
    {
        $email = trim(mb_strtolower($email));

        if ($email === '') {
            return null;
        }

        return Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    /**
     * Send a code, unless this address or this device has asked too often.
     *
     * Returns false only for rate limiting — NOT for an unknown address. The
     * caller shows the same words either way, so the screen cannot be used to
     * discover who works here.
     */
    public function send(?Employee $employee, string $email, ?string $ip = null): bool
    {
        $emailKey = 'staff-login-code:email:' . mb_strtolower(trim($email));
        $ipKey    = 'staff-login-code:ip:' . ($ip ?: 'unknown');

        if (RateLimiter::tooManyAttempts($emailKey, self::SENDS_PER_EMAIL)
            || RateLimiter::tooManyAttempts($ipKey, self::SENDS_PER_IP)) {
            return false;
        }

        // Counted even when the address matches nobody: otherwise the limiter
        // would only slow down real accounts, and someone probing a list of
        // addresses would be waved through.
        RateLimiter::hit($emailKey, self::SEND_WINDOW);
        RateLimiter::hit($ipKey, self::SEND_WINDOW);

        if (! $employee) {
            return true;
        }

        // An older code stops working the moment a new one is asked for, so a
        // forwarded email cannot be used behind the recipient's back.
        EmployeeLoginCode::where('employee_id', $employee->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generate();

        EmployeeLoginCode::create([
            'employee_id'  => $employee->id,
            'code_hash'    => Hash::make($code),
            'expires_at'   => now()->addMinutes(self::TTL_MINUTES),
            'requested_ip' => $ip,
        ]);

        Mail::to($employee->email)->send(new StaffLoginCodeMail($employee, $code, self::TTL_MINUTES));

        return true;
    }

    /**
     * Check a typed code against the live one.
     *
     * A wrong guess counts against that code and burns it at the limit, so a
     * six-digit secret cannot be walked through — and the person simply asks
     * for another, which costs them one email and an attacker the whole
     * search space again.
     */
    public function verify(Employee $employee, string $code): bool
    {
        $row = EmployeeLoginCode::where('employee_id', $employee->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $row) {
            return false;
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $row->update(['consumed_at' => now()]);

            return false;
        }

        if (! Hash::check(trim($code), $row->code_hash)) {
            $row->increment('attempts');

            return false;
        }

        $row->update(['consumed_at' => now()]);

        return true;
    }

    /**
     * Avoids the handful of values people read as "not a real code" — still
     * uniform over what is left, matching how PINs are generated here.
     */
    private function generate(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), self::LENGTH, '0', STR_PAD_LEFT);
        } while (in_array($code, ['000000', '111111', '123456', '654321', '999999'], true));

        return $code;
    }
}
