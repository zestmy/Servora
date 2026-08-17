<?php

namespace App\Http\Controllers\Labels;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A preparer's photograph, for the manager print screens' "Prepared by"
 * picker.
 *
 * ITS OWN ROUTE, for the same reason StaffPhotoController is its own route:
 * the audiences are gated differently and reusing a gate means either
 * widening it or leaving the screen blank. The print screens are behind
 * `labels.print`; the HR photo route is behind `hr.view` — and the person
 * standing at an outlet print station usually holds the first and not the
 * second, so handing this screen the HR route would fill the picker with
 * 403s wearing broken-image icons (the exact trap the avatar components'
 * notes warn about). The staff apps' PIN-session route is no better a fit:
 * preparers are outlet staff who need no PIN to be named on a label.
 *
 * Scope, drawn as tightly as the picker itself: `labels.print` on the route,
 * then the same two checks the picker's list applies — active, and at an
 * outlet the viewer can reach. CompanyScope rides on the model binding, so
 * another company's employee id is a 404 before any of this runs. The
 * profile photo only, never a document; streamed from the private disk.
 */
class PreparerPhotoController extends Controller
{
    public function show(Employee $employee): StreamedResponse|Response
    {
        abort_unless(
            $employee->is_active
            && in_array((int) $employee->outlet_id, Auth::user()->accessibleOutletIds(), true),
            404
        );

        abort_unless($employee->photo_path, 404);

        abort_unless(Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
