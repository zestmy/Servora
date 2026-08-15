<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\TrainingCourse;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The document a course was built from, for the person taking it.
 *
 * WHY THE ORIGINAL AND NOT THE EXTRACTED TEXT. The importer pulls the words out
 * of a PDF so the AI can read them, and that text is fine for a machine and
 * poor for a person: an SOP or a menu is laid out — tables, photographs of the
 * dish, the sections in the order somebody works through them — and extraction
 * flattens all of it into a wall of prose. Staff were being shown the wall
 * while the document it came from sat on disk.
 *
 * INLINE, not a download. This is meant to be read on the course page.
 *
 * Scoped exactly as the course screen is: same company, published, and visible
 * to one of this employee's outlets. A course somebody may not open is a
 * document they may not read, and the two rules must not be able to disagree —
 * so this asks the model the same question rather than a similar one.
 */
class CourseMaterialController extends Controller
{
    /*
     * The route parameter is read BY NAME. These routes are mounted on
     * {companySlug}.servora.com.my, so companySlug is the first parameter and
     * Laravel's dispatcher spreads them positionally — argument one would be
     * the slug. See StaffPhotoController for the full account.
     */
    public function show(Request $request, StaffSession $staff): StreamedResponse|Response
    {
        $employee = $staff->employee();

        abort_unless($employee, 403, 'Sign in again.');

        $course = TrainingCourse::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $employee->company_id)
            ->published()
            ->visibleToOutlets($employee->trainingOutletIds())
            ->find((int) $request->route('course'));

        abort_unless($course && $course->sourceIsPdf(), 404);
        abort_unless(Storage::disk('local')->exists($course->source_path), 404);

        return Storage::disk('local')->response(
            $course->source_path,
            $course->source_filename ?: 'course.pdf',
            [
                'Content-Type' => 'application/pdf',
                // Private and briefly cached: the file does not change, and a
                // reader scrolling through it should not re-fetch every page.
                'Cache-Control' => 'private, max-age=600',
            ],
            'inline',
        );
    }
}
