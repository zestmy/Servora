<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The chosen person's photograph, for the sign-in screen's confirmation step.
 *
 * PRE-AUTH, and that is a privacy decision stated plainly, the same way
 * StaffPhotoController states its own: nobody holds a session on the sign-in
 * screen, so this photo is visible to anyone who can load the company's
 * login page. The merchant asked for the face on the name-confirmation step
 * — tapping "AISYAH" and seeing Aisyah is what stops a PIN being typed
 * against the wrong name on a shared tablet — and whose photos a company
 * shows on its own doorway is the merchant's decision to make.
 *
 * THE FENCE EQUALS THE LOGIN SCREEN'S OWN LIST, condition for condition:
 * this company (from the subdomain), active, holding a PIN that has not
 * been switched off. Those are exactly the people whose NAMES the screen
 * already shows to the same unauthenticated visitor — so enumerating this
 * route yields nothing the dropdowns don't. Anyone outside that list —
 * email-only staff, deactivated staff, other companies — stays 404,
 * photo or no photo.
 *
 * Streamed from the PRIVATE disk, like every other staff photo route: the
 * file itself never moves to public storage.
 */
class LoginPhotoController extends Controller
{
    /*
     * The route parameter is read BY NAME, never taken as an argument: on the
     * production subdomain mount {companySlug} precedes {employee} and Laravel
     * spreads route parameters positionally. See StaffRouteParametersTest,
     * which enforces this shape for every staff route.
     */
    public function show(Request $request, StaffSession $staff): StreamedResponse
    {
        $employee  = (int) $request->route('employee');
        $companyId = $staff->companyId();

        abort_unless($companyId, 404);

        // No session to resolve CompanyScope from — company matched by hand,
        // like every query on the sign-in screen itself.
        $person = Employee::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('label_pin')
            ->whereNull('pin_login_disabled_at')
            ->find($employee);

        abort_unless($person && $person->photo_path, 404);

        abort_unless(Storage::disk('local')->exists($person->photo_path), 404);

        return Storage::disk('local')->response($person->photo_path, null, [
            // private: a doorway photo still doesn't belong in a shared cache.
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
