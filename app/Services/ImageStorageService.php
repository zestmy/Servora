<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Image upload processing: HEIC→JPEG conversion (iPhone photos) and
 * compression (resize + re-encode) for faster viewing and smaller storage.
 * Every path degrades gracefully — if imagick is missing or a file can't be
 * decoded, the original upload flow is used untouched.
 */
class ImageStorageService
{
    /** Formats browsers can't display; converted to JPEG at upload time. */
    public const CONVERTIBLE = ['heic', 'heif'];

    /** Longest image side after compression (px). */
    public const MAX_DIMENSION = 1600;

    /** JPEG/WEBP re-encode quality. */
    public const QUALITY = 80;

    public static function imagickAvailable(): bool
    {
        return class_exists(\Imagick::class);
    }

    /**
     * Convert a HEIC/HEIF Livewire temp upload to a JPEG temp upload, so the
     * instant preview renders and the mimes: validation passes downstream.
     * Returns the original file when conversion isn't possible — the caller's
     * previewability guard then rejects it with a friendly message.
     */
    public static function convertTempUploadToJpeg(TemporaryUploadedFile $file): TemporaryUploadedFile
    {
        if (! self::imagickAvailable()) {
            return $file;
        }

        try {
            $img = new \Imagick($file->getRealPath());
            self::autoOrient($img);
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(self::QUALITY);
            $blob = $img->getImageBlob();
            $img->clear();
        } catch (\Throwable $e) {
            report($e);
            return $file;
        }

        // Recreate Livewire's temp filename format with a .jpg identity so
        // previews, validation and save all see a plain JPEG upload.
        $newOriginal = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';
        $newFilename = Str::random(30)
            . str('-meta' . base64_encode($newOriginal) . '-')->replace('/', '_')
            . '.jpg';

        FileUploadConfiguration::storage()->put(
            FileUploadConfiguration::directory() . '/' . $newFilename,
            $blob,
        );

        $file->delete();

        return TemporaryUploadedFile::createFromLivewire($newFilename);
    }

    /**
     * Store an uploaded image compressed: auto-orient, strip metadata, cap
     * the longest side at MAX_DIMENSION and re-encode (JPEG/HEIC → jpg q80;
     * PNG/WEBP keep their format). Non-images, GIFs (animation would break)
     * and any imagick failure fall back to storing the original untouched.
     * Returns the stored path on the disk.
     */
    public static function storeCompressed($file, string $dir, string $disk = 'public'): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! self::imagickAvailable() || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)) {
            return $file->store($dir, $disk);
        }

        try {
            $img = new \Imagick($file->getRealPath());
            self::autoOrient($img);
            $img->stripImage();

            $w = $img->getImageWidth();
            $h = $img->getImageHeight();
            if (max($w, $h) > self::MAX_DIMENSION) {
                // 0 preserves aspect ratio for the other dimension.
                $img->resizeImage(
                    $w >= $h ? self::MAX_DIMENSION : 0,
                    $h > $w ? self::MAX_DIMENSION : 0,
                    \Imagick::FILTER_LANCZOS,
                    1,
                );
            }

            $targetExt = in_array($ext, ['png', 'webp'], true) ? $ext : 'jpg';
            $img->setImageFormat($targetExt === 'jpg' ? 'jpeg' : $targetExt);
            $img->setImageCompressionQuality(self::QUALITY);
            $blob = $img->getImageBlob();
            $img->clear();
        } catch (\Throwable $e) {
            report($e);
            return $file->store($dir, $disk);
        }

        $path = $dir . '/' . Str::random(40) . '.' . $targetExt;
        Storage::disk($disk)->put($path, $blob);

        return $path;
    }

    private static function autoOrient(\Imagick $img): void
    {
        // Method name differs across imagick releases.
        if (method_exists($img, 'autoOrient')) {
            $img->autoOrient();
        } elseif (method_exists($img, 'autoOrientImage')) {
            $img->autoOrientImage();
        }
    }
}
