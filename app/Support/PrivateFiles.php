<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files that must only be seen by someone signed in and allowed to see them:
 * sales attachments, supplier invoice scans, audit and corrective-action
 * photos.
 *
 * These used to live on the `public` disk, where the web server hands them to
 * anyone holding the URL — no login, no company check. They are now written
 * to the private `local` disk and streamed by App\Http\Controllers\
 * PrivateFileController, which checks the person first.
 *
 * Reads look on `local` first and fall back to `public`, so a file uploaded
 * before the move keeps working until the move migration has run over it.
 */
final class PrivateFiles
{
    public const DISK = 'local';

    /** The disk a stored path actually lives on, or null when it is on neither. */
    public static function diskFor(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /** Absolute filesystem path, for code that reads the bytes (AI extraction, PDFs). */
    public static function absolutePath(?string $path): ?string
    {
        $disk = self::diskFor($path);

        return $disk ? Storage::disk($disk)->path($path) : null;
    }

    /**
     * Stream it inline — these are looked at, not filed — with a name the
     * browser will accept and a cache that stays on the viewer's device.
     */
    public static function response(?string $path, ?string $name = null): StreamedResponse|Response
    {
        $disk = self::diskFor($path);
        abort_unless($disk, 404);

        $name = str_replace(['"', '/', '\\'], '-', $name ?: basename($path));

        return Storage::disk($disk)->response($path, $name, [
            'Content-Disposition' => 'inline; filename="' . $name . '"',
            'Cache-Control'       => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
