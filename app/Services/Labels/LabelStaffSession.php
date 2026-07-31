<?php

namespace App\Services\Labels;

use App\Models\Employee;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Session;

/**
 * Who is signed in to the staff label app.
 *
 * Deliberately NOT a Laravel auth guard. Guards carry password resets,
 * remember-me, and "user" semantics this doesn't have — an employee is not
 * a login, and conflating the two would let a PIN start behaving like an
 * account. This is a narrow session record with one job.
 *
 * The session survives indefinitely and is invalidated by the PIN changing,
 * not by time. The fingerprint recorded at sign-in is compared against the
 * employee's current PIN on every request, so a manager resetting a PIN
 * signs that person out everywhere.
 */
class LabelStaffSession
{
    private const KEY = 'label_staff';

    public function signIn(Employee $employee): void
    {
        // New session id on sign-in: a fixated session from before the PIN
        // was entered must not carry over into an authenticated one.
        Session::regenerate();

        Session::put(self::KEY, [
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'fingerprint' => $employee->labelPinFingerprint(),
        ]);
    }

    public function signOut(): void
    {
        Session::forget(self::KEY);
        Session::regenerate();
    }

    /**
     * The signed-in employee, or null.
     *
     * Returns null — rather than throwing — for every way a session can go
     * stale: employee deleted, deactivated, PIN revoked, PIN changed, or the
     * session belonging to a different company than the subdomain resolved.
     * All of those mean "sign in again", not "crash".
     */
    public function employee(?int $expectedCompanyId = null): ?Employee
    {
        $data = Session::get(self::KEY);

        if (! is_array($data) || empty($data['employee_id'])) {
            return null;
        }

        if ($expectedCompanyId && (int) ($data['company_id'] ?? 0) !== $expectedCompanyId) {
            return null;
        }

        // No authenticated web user here, so CompanyScope would match nothing.
        $employee = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('id', $data['employee_id'])
            ->where('is_active', true)
            ->first();

        if (! $employee || ! $employee->hasLabelPin()) {
            return null;
        }

        if (! hash_equals((string) $employee->labelPinFingerprint(), (string) ($data['fingerprint'] ?? ''))) {
            return null;
        }

        return $employee;
    }

    public function check(?int $expectedCompanyId = null): bool
    {
        return $this->employee($expectedCompanyId) !== null;
    }
}
