<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClockEvent;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves clock-in selfies and enrolment photos.
 *
 * These are photographs of staff taken at work, so they live on the private
 * disk and every request comes through here to be checked — first that the
 * viewer holds the right permission, then that the picture belongs to an
 * outlet they can see. A public URL would put a company's staff photos one
 * guessed path away from anyone on the internet, and the paths contain a
 * UUID precisely because someone would otherwise try.
 */
class ClockImageController extends Controller
{
    public function selfie(ClockEvent $event): StreamedResponse|Response
    {
        abort_unless(Auth::user()?->can('hr.clock'), 403);
        // (int) before the strict comparison: MySQL's PDO hands foreign keys
        // back as strings, so in_array("1", [1, 2], true) is false and every
        // selfie 403s into a blank box. Every other outlet check in the
        // codebase casts for exactly this reason.
        abort_unless(
            in_array((int) $event->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403
        );

        return $this->stream($event->selfie_path);
    }

    public function enrolment(EmployeeFaceDescriptor $descriptor): StreamedResponse|Response
    {
        abort_unless(Auth::user()?->can('hr.clock.manage'), 403);
        // Cast for the same reason as selfie() above. An employee with no
        // outlet casts to 0, which is refused — which is the right answer.
        abort_unless(
            in_array((int) $descriptor->employee?->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403
        );

        return $this->stream($descriptor->photo_path);
    }

    /**
     * A missing file is a 404, not a crash.
     *
     * Storage is swept and disks get replaced; a punch whose selfie has gone
     * must still be reviewable on its coordinates and its match score.
     */
    private function stream(?string $path): StreamedResponse|Response
    {
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            null,
            // Private and short-lived: the browser may hold it while the
            // review panel is open, no proxy may hold it at all.
            ['Cache-Control' => 'private, max-age=300, no-transform'],
        );
    }
}
