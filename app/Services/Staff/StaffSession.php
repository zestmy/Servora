<?php

namespace App\Services\Staff;

use App\Models\Employee;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Session;

/**
 * Who is signed in to a staff app — the labels PWA, the clock-in PWA, or
 * anything else an employee reaches with their staff PIN.
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
 *
 * ONE session across every staff app, by design. The PIN is the same secret
 * and the person is the same person; making a cook sign in twice on the same
 * phone to print a label and then clock in would be theatre, not security.
 * Which apps a PIN can reach is decided per app, not by holding separate
 * sessions.
 */
class StaffSession
{
    /**
     * Named for the labels app because it was the only staff app when this
     * was written. Kept verbatim on purpose: renaming it would sign out
     * every kitchen tablet in the field on the deploy that shipped it.
     */
    protected const KEY = 'label_staff';

    /**
     * @param  'pin'|'email'  $via  which credential opened this session
     */
    public function signIn(Employee $employee, string $via = 'pin'): void
    {
        // New session id on sign-in: a fixated session from before the PIN
        // was entered must not carry over into an authenticated one.
        Session::regenerate();

        Session::put(static::KEY, [
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'via'         => $via,
            'fingerprint' => $via === 'email'
                ? $employee->emailFingerprint()
                : $employee->labelPinFingerprint(),
        ]);
    }

    public function signOut(): void
    {
        Session::forget(static::KEY);
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
        $data = Session::get(static::KEY);

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

        /*
         * Validate against the credential that actually opened the session.
         *
         * This used to demand a PIN of everybody, which was fine while a PIN was
         * the only way in. It is not: on a real company 45 of 53 staff have an
         * email and no PIN, and requiring one here would sign them in and then
         * bounce them on their very next request — the session would look valid
         * to the screen that created it and invalid to every screen after.
         *
         * Sessions predating this carry no 'via' and are treated as PIN
         * sessions, so nobody already signed in is thrown out by the change.
         */
        $via = $data['via'] ?? 'pin';

        $fingerprint = $via === 'email'
            ? $employee->emailFingerprint()
            : ($employee->hasLabelPin() ? $employee->labelPinFingerprint() : null);

        if ($fingerprint === null) {
            return null;
        }

        if (! hash_equals((string) $fingerprint, (string) ($data['fingerprint'] ?? ''))) {
            return null;
        }

        return $employee;
    }

    public function check(?int $expectedCompanyId = null): bool
    {
        return $this->employee($expectedCompanyId) !== null;
    }

    /**
     * Which company this subdomain belongs to.
     *
     * Prefers the container binding, then falls back to what the subdomain
     * middleware stashed in the session. Belt and braces: any request that
     * somehow reaches a staff screen without that middleware having run
     * would otherwise look like the main domain and quietly return nothing.
     */
    public function companyId(): ?int
    {
        if (app()->bound('currentCompany')) {
            return (int) app('currentCompany')->id;
        }

        $fromSession = Session::get('subdomain_company_id');

        return $fromSession ? (int) $fromSession : null;
    }
}
