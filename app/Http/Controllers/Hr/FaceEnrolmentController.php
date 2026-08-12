<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFaceDescriptor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saving and deleting enrolled faces, over plain HTTP.
 *
 * Deliberately NOT Livewire actions. Enrolment is the gate on the whole
 * feature — nobody can clock in with a face check until their face is on
 * file — and it was silently failing because the capture went through
 * $wire, on a screen where Livewire's JavaScript was not running. A fetch
 * to a route works regardless.
 *
 * Same reasoning as the staff sign-out form and the enrolment name links:
 * an action that blocks the feature must not depend on Livewire booting.
 */
class FaceEnrolmentController extends Controller
{
    /** Below this a face is too poorly enrolled to be worth keeping. */
    public const MIN_CAPTURES = 3;

    /** Above this, more captures stop helping and just cost comparison time. */
    public const MAX_CAPTURES = 8;

    private const MAX_PHOTO_BYTES = 2_500_000;

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('hr.clock.manage'), 403);

        $employee = $this->employee((int) $request->input('employee_id'));

        if (! $employee) {
            return response()->json(['message' => 'That employee is not one you can enrol.'], 404);
        }

        $descriptor = $request->input('descriptor');

        // The vector arrives from a browser and is attacker-controlled: a
        // short array reaching the distance maths would score small against
        // everybody and pass.
        if (! EmployeeFaceDescriptor::isValidDescriptor($descriptor)) {
            return response()->json([
                'message' => 'That capture could not be read. Try again with the face filling more of the frame.',
            ], 422);
        }

        $existing = EmployeeFaceDescriptor::where('employee_id', $employee->id)->count();

        if ($existing >= self::MAX_CAPTURES) {
            return response()->json([
                'message' => 'That is already ' . self::MAX_CAPTURES . ' captures — plenty. Delete one first to replace it.',
            ], 422);
        }

        $capture = EmployeeFaceDescriptor::create([
            'company_id'    => $employee->company_id,
            'employee_id'   => $employee->id,
            'descriptor'    => array_map('floatval', $descriptor),
            'model_version' => EmployeeFaceDescriptor::MODEL_VERSION,
            'photo_path'    => $this->storePhoto($employee, $request->input('photo')),
            'enrolled_by'   => Auth::id(),
        ]);

        return response()->json([
            'id'         => $capture->id,
            'photo_url'  => $capture->photo_path ? route('hr.face-enrolment.photo', $capture) : null,
            // So a capture taken seconds ago can be deleted without a reload —
            // the usual reason to delete one is that you just saw it was bad.
            'delete_url' => route('hr.face-enrolment.delete', $capture),
            'count'      => $existing + 1,
            'enough'     => ($existing + 1) >= self::MIN_CAPTURES,
        ]);
    }

    /** A form post, so a bad capture can be removed with no JavaScript. */
    public function destroy(EmployeeFaceDescriptor $descriptor): RedirectResponse
    {
        abort_unless(Auth::user()?->can('hr.clock.manage'), 403);

        $employee = $this->employee((int) $descriptor->employee_id);

        abort_unless($employee, 403);

        // The image is removed by the model's `deleted` hook, so it goes from
        // wherever a descriptor is deleted rather than only from here.
        $descriptor->delete();

        return redirect()
            ->route('hr.face-enrolment', ['employee' => $employee->id])
            ->with('success', 'Capture deleted.');
    }

    /** Scoped to outlets this user may see. */
    private function employee(int $id): ?Employee
    {
        if (! $id) {
            return null;
        }

        return Employee::whereIn('outlet_id', Auth::user()->accessibleOutletIds() ?: [0])
            ->find($id);
    }

    /** The reference photo, on the private disk — never public. */
    private function storePhoto(Employee $employee, mixed $dataUrl): ?string
    {
        if (! is_string($dataUrl) || ! preg_match('#^data:image/(jpeg|jpg|png);base64,#', $dataUrl, $m)) {
            return null;
        }

        if (strlen($dataUrl) > self::MAX_PHOTO_BYTES) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        // The declared MIME is the browser's word for it; check the bytes.
        if ($binary === false || $binary === '' || ! @getimagesizefromstring($binary)) {
            return null;
        }

        $path = sprintf(
            'face-enrolments/%d/%d/%s.%s',
            $employee->company_id, $employee->id, Str::uuid(), $m[1] === 'png' ? 'png' : 'jpg'
        );

        return Storage::disk('local')->put($path, $binary) ? $path : null;
    }
}
