<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves employee photographs and scanned paperwork.
 *
 * These are IC scans and staff photographs, so they live on the private disk
 * and every request comes through here to be checked — first the permission,
 * then that the employee is at an outlet the viewer can see. A public URL would
 * put a company's identity documents one guessed path away from anyone on the
 * internet, which is the same reasoning behind ClockImageController.
 */
class EmployeeDocumentController extends Controller
{
    public function photo(Employee $employee): StreamedResponse|Response
    {
        $this->authorise($employee);

        return $this->stream($employee->photo_path, inline: true);
    }

    public function show(EmployeeDocument $document): StreamedResponse|Response
    {
        $this->authorise($document->employee);

        return $this->stream($document->file_path, inline: true, name: $document->original_name);
    }

    public function download(EmployeeDocument $document): StreamedResponse|Response
    {
        $this->authorise($document->employee);

        abort_if(! Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    /**
     * hr.view plus outlet access — the same gate the employee's own record is
     * behind, because a document is only as private as the record it hangs off.
     */
    private function authorise(?Employee $employee): void
    {
        abort_unless(Auth::user()?->can('hr.view'), 403);
        abort_if(! $employee, 404);
        // (int) before the strict comparison: PDO hands foreign keys back as
        // strings, so in_array("1", [1, 2], true) is false and every document
        // 403s into a blank box.
        abort_unless(
            in_array((int) $employee->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403
        );
    }

    /** A missing file is a 404, not a crash — storage gets swept. */
    private function stream(?string $path, bool $inline = false, ?string $name = null): StreamedResponse|Response
    {
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $name,
            // Private and short-lived: the browser may hold it while the form
            // is open, no proxy may hold it at all.
            ['Cache-Control' => 'private, max-age=300, no-transform'],
            $inline ? 'inline' : 'attachment',
        );
    }
}
