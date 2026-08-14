<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A colleague's photograph, for the boards inside the Staff Portal.
 *
 * A SEPARATE ROUTE FROM hr.employees.photo, and not for convenience. That one
 * is gated on `hr.view` plus outlet access, because it serves a personnel file
 * to a manager; this one is gated on holding a PIN for the same company,
 * because it serves a face to a colleague. Reusing the manager's route would
 * have meant either handing PIN sessions an HR ability or leaving the portal
 * with initials, and neither is the thing that was asked for.
 *
 * WHAT THIS CHANGES, stated plainly because it is a privacy decision and not a
 * UI tweak: every person with a PIN can now load the staff photo of anyone in
 * their company. The boards were built with initials for exactly this reason.
 * The merchant asked for the photographs, which is theirs to decide — and the
 * scope is drawn as tightly as the request allows: this company only, the
 * profile photo only (never a document, never a clock-in selfie or a face
 * enrolment frame), and only for somebody already through the PIN gate.
 *
 * Streamed from the PRIVATE disk. The photo never moves to public storage, so
 * there is no URL that works without a session behind it.
 */
class StaffPhotoController extends Controller
{
    public function show(int $employee, StaffSession $staff): StreamedResponse|Response
    {
        $companyId = $staff->companyId();

        abort_unless($companyId, 404);

        // The scope is dropped and company_id matched by hand: a PIN session is
        // not a Laravel guard, so CompanyScope has nothing to resolve from.
        $person = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->find($employee);

        abort_unless($person && $person->photo_path, 404);

        abort_unless(Storage::disk('local')->exists($person->photo_path), 404);

        return Storage::disk('local')->response($person->photo_path, null, [
            // A face does not change between one board render and the next, and
            // the boards poll. `private` keeps it out of any shared cache.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
