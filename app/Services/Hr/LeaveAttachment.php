<?php

namespace App\Services\Hr;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The medical certificate behind a leave request.
 *
 * TWO SCREENS APPLY FOR LEAVE — the staff portal and HR's own form — and they
 * agree about nothing else: one has a PIN session and no authenticated user,
 * the other has a manager filling in somebody else's absence. Storing the file
 * is the one thing they must do identically, so it lives here rather than
 * twice.
 *
 * PRIVATE DISK, ALWAYS. An MC carries a name, a diagnosis often enough, a
 * clinic and a date. A public path would put that one guess away from anyone
 * on the internet — the same reasoning as EmployeeDocumentController, and the
 * reason both readers of these files go through a controller that checks.
 */
class LeaveAttachment
{
    /** What a phone camera or a clinic's printer actually produces. */
    public const ACCEPTS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf'];

    /** Megabytes. A photograph of a slip, not a scan of a folder. */
    public const MAX_MB = 8;

    /** The Livewire validation rule both forms use, so they cannot drift. */
    public static function rule(): array
    {
        return ['nullable', 'file', 'mimes:' . implode(',', self::ACCEPTS), 'max:' . (self::MAX_MB * 1024)];
    }

    public static function accepted(): string
    {
        return '.' . implode(',.', self::ACCEPTS);
    }

    /**
     * Store the upload and return [path, original name].
     *
     * Filed per company and per employee so a company's documents can be found
     * — and deleted — as a set, which is what a data request asks for.
     *
     * @return array{0: string, 1: string}
     */
    public static function store(TemporaryUploadedFile $file, int $companyId, int $employeeId): array
    {
        $original = $file->getClientOriginalName();

        $path = $file->storeAs(
            'leave-attachments/' . $companyId . '/' . $employeeId,
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'local'
        );

        return [$path, Str::limit($original, 120, '')];
    }

    /**
     * Replace what a request already carries, removing the old file.
     *
     * An MC re-uploaded is usually the first one photographed badly. Keeping
     * both would leave an orphan nobody can see and nobody deletes.
     */
    public static function replace(LeaveRequest $request, TemporaryUploadedFile $file): void
    {
        $old = $request->attachment_path;

        [$path, $name] = self::store($file, $request->company_id, $request->employee_id);

        $request->update(['attachment_path' => $path, 'attachment_name' => $name]);

        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }
}
