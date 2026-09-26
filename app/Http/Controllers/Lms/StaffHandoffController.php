<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LmsUser;
use App\Services\Staff\StaffSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opens the SOP library for whoever is signed in to the Staff Portal.
 *
 * The LMS has its own accounts — register, then a manager approves — and every
 * screen in it reads an LmsUser: the company scope, the outlet filter on SOPs,
 * the audit log. So rather than teach all of that a second identity, a staff
 * session is mapped onto an LmsUser and signed in to the `lms` guard, and the
 * LMS carries on exactly as before.
 *
 * The account is found in this order:
 *   1. one already linked to this employee;
 *   2. one registered with the employee's email — linked on first use, so a
 *      person who registered before this existed keeps their outlet access;
 *   3. otherwise a new one, approved, on the employee's outlet, with no
 *      password: the PIN is the credential.
 *
 * A manager's REJECTION stands. Being on the payroll does not overrule
 * somebody who looked at this person's registration and said no.
 *
 * Access opened this way lasts only as long as the staff session behind it —
 * LmsAuthenticate drops it the moment that session stops being valid (signed
 * out, PIN changed, employee deactivated).
 */
class StaffHandoffController extends Controller
{
    /** Session key: the employee whose staff session opened the LMS one. */
    public const SESSION_KEY = 'lms_via_staff';

    public function __invoke(StaffSession $staff): RedirectResponse
    {
        // The route sits behind clock.staff.auth, so this is never null here.
        $employee = $staff->employee($staff->companyId());

        $lmsUser = $this->accountFor($employee);

        if ($lmsUser->status === 'rejected') {
            return redirect()->route('clock.staff.home')
                ->with('error', 'Your SOP Library access was turned down. Please speak to your manager.');
        }

        Auth::guard('lms')->login($lmsUser);

        session([
            self::SESSION_KEY  => $employee->id,
            'lms_company_slug' => $employee->company?->slug,
        ]);

        return redirect()->route('lms.dashboard');
    }

    private function accountFor(Employee $employee): LmsUser
    {
        return DB::transaction(function () use ($employee) {
            $linked = LmsUser::where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate()
                ->first();

            if ($linked) {
                return $this->approvePending($linked);
            }

            $email = trim((string) $employee->email);

            if ($email !== '') {
                $byEmail = LmsUser::where('company_id', $employee->company_id)
                    ->whereNull('employee_id')
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($byEmail) {
                    $byEmail->update(['employee_id' => $employee->id]);

                    return $this->approvePending($byEmail);
                }
            }

            return LmsUser::create([
                'company_id'  => $employee->company_id,
                'outlet_id'   => $employee->outlet_id,
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                // Only when no other account in this company already uses it —
                // checked above for unlinked ones, and a linked one with this
                // email belongs to somebody else's employee record.
                'email'       => $email !== '' && ! LmsUser::where('company_id', $employee->company_id)
                    ->where('email', $email)->exists() ? $email : null,
                'password'    => null,
                'phone'       => $employee->phone,
                'status'      => 'approved',
                'approved_at' => now(),
            ]);
        });
    }

    /**
     * A registration still waiting for a manager is let through: the staff
     * session is proof enough that this is one of the company's own people,
     * which is the question the approval queue exists to answer. Stamped with
     * no approver, which is how the Training Portal settings tell the two
     * kinds of approval apart.
     */
    private function approvePending(LmsUser $user): LmsUser
    {
        if ($user->status === 'pending') {
            $user->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => null]);
        }

        return $user;
    }
}
